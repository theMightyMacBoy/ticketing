<?php

use Carbon\Carbon;
use Coderflex\LaravelTicket\Desk\TicketDesk;
use Coderflex\LaravelTicket\Enums\LinkType;
use Coderflex\LaravelTicket\Events\TicketSlaBreached;
use Coderflex\LaravelTicket\Exceptions\ApprovalRequired;
use Coderflex\LaravelTicket\Exceptions\InvalidTicketTransition;
use Coderflex\LaravelTicket\Exceptions\MissingCustomFields;
use Coderflex\LaravelTicket\Exceptions\ReplyNotAllowed;
use Coderflex\LaravelTicket\Models\AssignmentGroup;
use Coderflex\LaravelTicket\Models\Category;
use Coderflex\LaravelTicket\Models\ConfigurationItem;
use Coderflex\LaravelTicket\Models\CustomFieldDefinition;
use Coderflex\LaravelTicket\Models\Organization;
use Coderflex\LaravelTicket\Models\ResolutionCode;
use Coderflex\LaravelTicket\Models\Service;
use Coderflex\LaravelTicket\Models\SlaPolicy;
use Coderflex\LaravelTicket\Models\Ticket;
use Coderflex\LaravelTicket\Reporting\ServiceDeskReport;
use Coderflex\LaravelTicket\Tests\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

it('opens an incident with a number, a uuid, and a scored priority', function () {
    $user = User::factory()->create();
    $organization = Organization::query()->create([
        'name' => 'Acme',
        'slug' => 'acme',
    ]);

    $ticket = app(TicketDesk::class)->open([
        'title' => 'Email down',
        'message' => str_repeat('The exchange cluster is unreachable. ', 20),
        'type' => 'incident',
        'impact' => 'high',
        'urgency' => 'critical',
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'channel' => 'email',
        'is_major' => true,
    ], $user);

    expect($ticket->ticket_number)->toStartWith('INC-')
        ->and($ticket->uuid)->not->toBeEmpty()
        ->and($ticket->status)->toBe('new')
        ->and($ticket->priority)->toBe('critical')
        ->and($ticket->is_major)->toBeTrue()
        ->and($ticket->organization_id)->toBe($organization->id)
        ->and(strlen((string) $ticket->message))->toBeGreaterThan(255)
        ->and($ticket->response_due_at)->not->toBeNull()
        ->and($ticket->activities()->where('event', 'opened')->exists())->toBeTrue();
});

it('numbers each ticket type from its own sequence', function () {
    $change = Ticket::factory()->create(['type' => 'change', 'status' => 'new']);
    $problem = Ticket::factory()->create(['type' => 'problem', 'status' => 'new']);

    expect($change->ticket_number)->toBe('CHG-000001')
        ->and($problem->ticket_number)->toBe('PRB-000001');
});

it('refuses an illegal status move and requires approval before a change starts', function () {
    $archived = Ticket::factory()->create(['status' => 'archived']);

    expect(fn () => $archived->start())->toThrow(InvalidTicketTransition::class);

    $change = Ticket::factory()->create([
        'type' => 'change',
        'status' => 'new',
    ]);
    $approver = User::factory()->create();

    expect(fn () => $change->start())->toThrow(ApprovalRequired::class);

    $change->requestApproval($approver)->approve('Maintenance window is clear.');

    $change->start();

    expect($change->fresh()->isInProgress())->toBeTrue();
});

it('lets an incident move through pending and records the pause', function () {
    Carbon::setTestNow('2026-06-01 12:00:00');

    SlaPolicy::query()->create([
        'name' => 'Incident low',
        'priority' => 'low',
        'ticket_type' => 'incident',
        'response_minutes' => 60,
        'resolution_minutes' => 120,
        'use_business_hours' => false,
        'is_default' => false,
    ]);

    $ticket = Ticket::factory()->create([
        'type' => 'incident',
        'priority' => 'low',
        'status' => 'open',
    ]);

    expect($ticket->response_due_at->utc()->format('Y-m-d H:i'))->toBe('2026-06-01 13:00')
        ->and($ticket->resolution_due_at->utc()->format('Y-m-d H:i'))->toBe('2026-06-01 14:00');

    Carbon::setTestNow('2026-06-01 12:10:00');
    $ticket->pend('customer');

    Carbon::setTestNow('2026-06-01 12:40:00');
    $ticket->start();

    $ticket->refresh();

    expect($ticket->isInProgress())->toBeTrue()
        ->and($ticket->pending_reason)->toBeNull()
        ->and($ticket->sla_paused_minutes)->toBe(30)
        ->and($ticket->response_due_at->utc()->format('Y-m-d H:i'))->toBe('2026-06-01 13:30')
        ->and($ticket->resolution_due_at->utc()->format('Y-m-d H:i'))->toBe('2026-06-01 14:30');
});

it('counts a public agent reply as the first response and ignores internal notes', function () {
    $requester = User::factory()->create();
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'user_id' => $requester->id,
        'status' => 'open',
    ]);

    $ticket->messageAsUser($requester, 'It is still down.');
    $ticket->note('Checked the cluster.', $agent);

    expect($ticket->fresh()->first_response_at)->toBeNull()
        ->and($ticket->messages()->internal()->count())->toBe(1);

    $ticket->messageAsUser($agent, 'We are failing over.');

    expect($ticket->fresh()->first_response_at)->not->toBeNull()
        ->and($ticket->activities()->where('event', 'reply')->exists())->toBeTrue()
        ->and($ticket->activities()->where('event', 'note')->exists())->toBeTrue();
});

it('blocks public replies on a locked ticket unless the user is the assignee or an agent', function () {
    $requester = User::factory()->create();
    $assignee = User::factory()->create();
    $agent = User::factory()->create();
    $agent->managesTickets = true;

    $ticket = Ticket::factory()->create([
        'user_id' => $requester->id,
        'status' => 'open',
        'is_locked' => true,
        'assigned_to' => $assignee->id,
    ]);

    expect(fn () => $ticket->messageAsUser($requester, 'Any update?'))
        ->toThrow(ReplyNotAllowed::class);

    $ticket->note('Still locked for the requester.', $requester);
    $ticket->messageAsUser($assignee, 'Assignee reply.');
    $ticket->messageAsUser($agent, 'Agent reply.');

    expect($ticket->messages()->public()->count())->toBe(2)
        ->and($ticket->messages()->internal()->count())->toBe(1);
});

it('writes an audit row when the status changes', function () {
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $ticket->close();

    $activity = $ticket->activities()->where('event', 'status_changed')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['status']['from'])->toBe('open')
        ->and($activity->properties['status']['to'])->toBe('closed')
        ->and($ticket->fresh()->closed_at)->not->toBeNull();
});

it('links parent, child, and duplicate tickets', function () {
    $parent = Ticket::factory()->create(['type' => 'problem', 'status' => 'open']);
    $child = Ticket::factory()->create(['type' => 'incident', 'status' => 'open']);

    $parent->addChild($child);
    $child->markDuplicateOf($parent);

    expect($parent->childTickets()->first()->is($child))->toBeTrue()
        ->and($child->parentTickets()->first()->is($parent))->toBeTrue()
        ->and($child->linkedTickets(LinkType::DUPLICATE)->first()->is($parent))->toBeTrue()
        ->and(fn () => $parent->relate($parent))->toThrow(InvalidArgumentException::class);
});

it('limits the queue to people involved with the ticket', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $member = User::factory()->create();
    $group = AssignmentGroup::query()->create([
        'name' => 'Network',
        'slug' => 'network',
    ]);
    $group->addMember($member);

    $ticket = Ticket::factory()->create([
        'user_id' => $owner->id,
        'status' => 'open',
        'assignment_group_id' => $group->id,
    ]);

    expect($owner->can('view', $ticket))->toBeTrue()
        ->and($owner->can('update', $ticket))->toBeFalse()
        ->and($stranger->can('view', $ticket))->toBeFalse()
        ->and($member->can('update', $ticket))->toBeTrue()
        ->and(Ticket::query()->visibleTo($member)->pluck('id'))->toContain($ticket->id);

    $ticket->watch($stranger);

    expect($stranger->can('view', $ticket))->toBeTrue()
        ->and($stranger->can('update', $ticket))->toBeFalse();

    $stranger->managesTickets = true;

    expect($stranger->can('delete', $ticket))->toBeTrue();
});

it('routes a service to its assignment group and records work against a configuration item', function () {
    $group = AssignmentGroup::query()->create([
        'name' => 'Storage',
        'slug' => 'storage',
    ]);
    $service = Service::query()->create([
        'name' => 'Mail',
        'slug' => 'mail',
        'assignment_group_id' => $group->id,
    ]);
    $item = ConfigurationItem::query()->create([
        'service_id' => $service->id,
        'name' => 'mail-01',
        'slug' => 'mail-01',
        'type' => 'server',
    ]);
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $ticket->assignService($service);
    $ticket->attachConfigurationItem($item);
    $ticket->logWork(45, 'Failed the database over.', $agent);

    $fresh = $ticket->fresh();

    expect($fresh->service_id)->toBe($service->id)
        ->and($fresh->assignment_group_id)->toBe($group->id)
        ->and($fresh->configurationItems)->toHaveCount(1)
        ->and((int) $fresh->workLogs()->sum('minutes'))->toBe(45);
});

it('requires custom fields and a resolution code can be stored on resolve', function () {
    CustomFieldDefinition::query()->create([
        'name' => 'Hostname',
        'key' => 'hostname',
        'ticket_type' => 'incident',
        'data_type' => 'string',
        'is_required' => true,
    ]);

    $ticket = Ticket::factory()->create([
        'type' => 'incident',
        'status' => 'open',
    ]);

    expect(fn () => $ticket->close())->toThrow(MissingCustomFields::class);

    $ticket->setCustom('hostname', 'mail-01');
    $code = ResolutionCode::query()->where('slug', 'solved')->first();
    $ticket->resolve($code);

    expect($ticket->fresh()->isResolved())->toBeTrue()
        ->and($ticket->fresh()->status)->toBe('resolved')
        ->and($ticket->fresh()->resolution_code_id)->toBe($code->id)
        ->and($ticket->fresh()->custom_attributes['hostname'])->toBe('mail-01');
});

it('rejects a custom value that does not match the field type', function () {
    CustomFieldDefinition::query()->create([
        'name' => 'Impacted sites',
        'key' => 'sites',
        'data_type' => 'number',
        'is_required' => false,
    ]);

    $ticket = Ticket::factory()->create(['status' => 'open']);

    expect(fn () => $ticket->setCustom('sites', 'many'))->toThrow(InvalidArgumentException::class);
});

it('stores an attachment and nests categories', function () {
    Storage::fake('public');

    $parent = Category::factory()->create(['name' => 'Network', 'slug' => 'network-root']);
    $child = Category::factory()->create([
        'name' => 'VPN',
        'slug' => 'vpn',
        'parent_id' => $parent->id,
    ]);
    $ticket = Ticket::factory()->create(['status' => 'open']);
    $ticket->attachCategories($child);

    $attachment = $ticket->attachFile(UploadedFile::fake()->create('error.log', 12));

    Storage::disk('public')->assertExists($attachment->path);

    expect($attachment->original_name)->toBe('error.log')
        ->and($parent->children)->toHaveCount(1)
        ->and(Category::query()->roots()->whereKey($child->id)->exists())->toBeFalse()
        ->and($ticket->categories()->count())->toBe(1);
});

it('reports attainment, backlog, and mean time to resolve', function () {
    Carbon::setTestNow('2026-06-01 12:00:00');

    $fast = Ticket::factory()->create([
        'type' => 'incident',
        'priority' => 'low',
        'status' => 'open',
    ]);
    $fast->resolve();

    Carbon::setTestNow('2026-06-01 14:00:00');
    $slow = Ticket::factory()->create([
        'type' => 'incident',
        'priority' => 'critical',
        'status' => 'open',
    ]);

    Carbon::setTestNow('2026-06-02 12:00:00');
    $slow->resolve();

    Ticket::factory()->create([
        'type' => 'service_request',
        'priority' => 'low',
        'status' => 'open',
    ]);

    $report = app(ServiceDeskReport::class);

    expect($report->backlog())->toBe(1)
        ->and($report->slaAttainment())->toBe(50.0)
        ->and($report->meanTimeToResolveMinutes())->toBe(660.0)
        ->and($report->volumeByType()['incident'])->toBe(2)
        ->and($report->volumeByType()['service_request'])->toBe(1);
});

it('marks a response breach from the scheduler without breaching resolution', function () {
    Event::fake([TicketSlaBreached::class]);
    Carbon::setTestNow('2026-06-01 12:00:00');

    $ticket = Ticket::factory()->create([
        'type' => 'incident',
        'priority' => 'critical',
        'status' => 'open',
    ]);

    Carbon::setTestNow('2026-06-01 12:20:00');

    $this->artisan('tickets:evaluate-slas')->assertSuccessful();

    $ticket->refresh();

    expect($ticket->response_breached)->toBeTrue()
        ->and($ticket->resolution_breached)->toBeFalse();

    Event::assertDispatched(TicketSlaBreached::class);
});

it('uses the business calendar when the policy asks for it', function () {
    Carbon::setTestNow('2026-06-05 16:00:00');

    SlaPolicy::query()->create([
        'name' => 'Business high',
        'priority' => 'high',
        'ticket_type' => 'incident',
        'response_minutes' => 120,
        'resolution_minutes' => 240,
        'use_business_hours' => true,
        'is_default' => false,
    ]);

    $ticket = Ticket::factory()->create([
        'type' => 'incident',
        'priority' => 'high',
        'status' => 'open',
    ]);

    expect($ticket->response_due_at->utc()->format('Y-m-d H:i'))->toBe('2026-06-08 10:00');
});
