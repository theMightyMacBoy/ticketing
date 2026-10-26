<?php

use Carbon\Carbon;
use Coderflex\LaravelTicket\Desk\TicketDesk;
use Coderflex\LaravelTicket\Exceptions\ApprovalRequired;
use Coderflex\LaravelTicket\Exceptions\MissingCustomFields;
use Coderflex\LaravelTicket\Models\AssignmentGroup;
use Coderflex\LaravelTicket\Models\CustomFieldDefinition;
use Coderflex\LaravelTicket\Models\Department;
use Coderflex\LaravelTicket\Models\Service;
use Coderflex\LaravelTicket\Models\SlaPolicy;
use Coderflex\LaravelTicket\Models\Ticket;
use Coderflex\LaravelTicket\Reporting\ServiceDeskReport;
use Coderflex\LaravelTicket\Sla\BusinessHours;
use Coderflex\LaravelTicket\Tests\Models\User;
use Illuminate\Support\Facades\Gate;

it('keeps a department agent inside that department', function () {
    $it = Department::query()->create(['name' => 'IT', 'slug' => 'it']);
    $hr = Department::query()->create(['name' => 'HR', 'slug' => 'hr']);
    $itAgent = User::factory()->create();
    $hrAgent = User::factory()->create();
    $it->addAgent($itAgent);
    $hr->addAgent($hrAgent);

    $complaint = Ticket::factory()->create([
        'department_id' => $hr->id,
        'title' => 'Complaint',
        'status' => 'new',
    ]);

    expect(Ticket::query()->visibleTo($itAgent)->whereKey($complaint->id)->exists())->toBeFalse()
        ->and(Ticket::query()->visibleTo($hrAgent)->whereKey($complaint->id)->exists())->toBeTrue()
        ->and(Gate::forUser($itAgent)->allows('view', $complaint))->toBeFalse()
        ->and(Gate::forUser($hrAgent)->allows('update', $complaint))->toBeTrue();

    $global = User::factory()->create();
    $global->managesTickets = true;

    expect(Ticket::query()->visibleTo($global)->whereKey($complaint->id)->exists())->toBeTrue();
});

it('numbers each department type from its own prefix', function () {
    $hr = Department::query()->create(['name' => 'HR', 'slug' => 'hr']);
    $hr->defineType('Case', 'CASE');
    $requester = User::factory()->create();

    $case = app(TicketDesk::class)->open([
        'title' => 'New hire',
        'type' => 'case',
        'department_id' => $hr->id,
        'user_id' => $requester->id,
    ], $requester);

    $incident = Ticket::factory()->create(['type' => 'incident', 'status' => 'new']);

    expect($case->ticket_number)->toBe('CASE-000001')
        ->and($incident->ticket_number)->toBe('INC-000001');
});

it('requires catalog fields and opens the ticket on the offering queue', function () {
    $it = Department::query()->create(['name' => 'IT', 'slug' => 'it']);
    $network = AssignmentGroup::query()->create([
        'name' => 'Network',
        'slug' => 'network',
        'department_id' => $it->id,
    ]);
    $vpn = Service::query()->create([
        'name' => 'VPN',
        'slug' => 'vpn',
        'department_id' => $it->id,
        'assignment_group_id' => $network->id,
    ]);
    CustomFieldDefinition::query()->create([
        'name' => 'Hostname',
        'key' => 'hostname',
        'service_id' => $vpn->id,
        'data_type' => 'string',
        'is_required' => true,
    ]);
    $requester = User::factory()->create();

    expect(fn () => app(TicketDesk::class)->open([
        'title' => 'VPN access',
        'service_id' => $vpn->id,
        'user_id' => $requester->id,
    ], $requester))->toThrow(MissingCustomFields::class);

    $ticket = app(TicketDesk::class)->open([
        'title' => 'VPN access',
        'service_id' => $vpn->id,
        'user_id' => $requester->id,
        'custom_attributes' => ['hostname' => 'edge-1'],
    ], $requester);

    expect($ticket->department_id)->toBe($it->id)
        ->and($ticket->assignment_group_id)->toBe($network->id)
        ->and($ticket->custom_attributes['hostname'])->toBe('edge-1');
});

it('counts sla minutes on the department calendar', function () {
    config()->set('laravel_ticket.business_hours', [
        'timezone' => 'UTC',
        'week' => [
            'monday' => ['start' => '09:00', 'end' => '17:00'],
            'tuesday' => ['start' => '09:00', 'end' => '17:00'],
            'wednesday' => ['start' => '09:00', 'end' => '17:00'],
            'thursday' => ['start' => '09:00', 'end' => '17:00'],
            'friday' => ['start' => '09:00', 'end' => '17:00'],
        ],
        'holidays' => [],
    ]);

    $facilities = Department::query()->create(['name' => 'Facilities', 'slug' => 'facilities']);
    $facilities->setCalendar([
        'saturday' => ['start' => '09:00', 'end' => '17:00'],
    ]);

    $saturday = Carbon::parse('2026-06-06 16:00:00', 'UTC');
    $global = new BusinessHours;
    $local = (new BusinessHours)->using($facilities->calendar()->first()?->toSchedule());

    expect($global->addMinutes($saturday, 60)->format('Y-m-d H:i'))->toBe('2026-06-08 10:00')
        ->and($local->addMinutes($saturday, 60)->format('Y-m-d H:i'))->toBe('2026-06-06 17:00');

    SlaPolicy::query()->create([
        'name' => 'Facilities low',
        'department_id' => $facilities->id,
        'priority' => 'low',
        'response_minutes' => 60,
        'resolution_minutes' => 120,
        'use_business_hours' => true,
    ]);

    Carbon::setTestNow($saturday);

    $ticket = Ticket::factory()->create([
        'department_id' => $facilities->id,
        'priority' => 'low',
        'status' => 'new',
        'type' => 'incident',
    ]);

    expect($ticket->response_due_at?->format('Y-m-d H:i'))->toBe('2026-06-06 17:00');
});

it('walks an approval chain before work starts', function () {
    $hr = Department::query()->create(['name' => 'HR', 'slug' => 'hr']);
    $hr->defineType('Access', 'ACC', true);
    $requester = User::factory()->create();
    $manager = User::factory()->create();
    $director = User::factory()->create();

    $ticket = app(TicketDesk::class)->open([
        'title' => 'Building access',
        'type' => 'access',
        'department_id' => $hr->id,
        'user_id' => $requester->id,
    ], $requester);

    $steps = $ticket->requestApprovalChain([$manager, $director]);

    expect(fn () => $ticket->start())->toThrow(ApprovalRequired::class);

    $steps[0]->approve('Manager ok');

    expect($steps[1]->fresh()->status)->toBe('pending');
    expect(fn () => $ticket->fresh()->start())->toThrow(ApprovalRequired::class);

    $steps[1]->fresh()->approve('Director ok');
    $ticket->fresh()->start();

    expect($ticket->fresh()->status)->toBe('in_progress');
});

it('moves work to another department without copying the case', function () {
    $hr = Department::query()->create(['name' => 'HR', 'slug' => 'hr']);
    $it = Department::query()->create(['name' => 'IT', 'slug' => 'it']);
    $hr->defineType('Case', 'CASE');
    $desktop = AssignmentGroup::query()->create([
        'name' => 'Desktop',
        'slug' => 'desktop',
        'department_id' => $it->id,
    ]);
    $requester = User::factory()->create();

    $parent = app(TicketDesk::class)->open([
        'title' => 'Onboarding',
        'type' => 'case',
        'department_id' => $hr->id,
        'user_id' => $requester->id,
    ], $requester);

    $child = $parent->spawnTask([
        'title' => 'Laptop',
        'type' => 'incident',
        'user_id' => $requester->id,
    ], $desktop);

    expect($child->department_id)->toBe($it->id)
        ->and($child->assignment_group_id)->toBe($desktop->id)
        ->and($child->ticket_number)->toStartWith('INC-')
        ->and($parent->childTickets()->first()?->is($child))->toBeTrue();

    $parent->transferTo($desktop);

    expect($parent->fresh()->department_id)->toBe($it->id)
        ->and($parent->fresh()->assignment_group_id)->toBe($desktop->id)
        ->and($parent->fresh()->ticket_number)->toBe('CASE-000001');
});

it('limits a report to one department', function () {
    $hr = Department::query()->create(['name' => 'HR', 'slug' => 'hr']);
    $it = Department::query()->create(['name' => 'IT', 'slug' => 'it']);

    Ticket::factory()->create(['department_id' => $hr->id, 'status' => 'new']);
    Ticket::factory()->create(['department_id' => $it->id, 'status' => 'new']);

    $report = app(ServiceDeskReport::class);

    expect($report->backlog())->toBe(2)
        ->and($report->forDepartment($hr)->backlog())->toBe(1);
});
