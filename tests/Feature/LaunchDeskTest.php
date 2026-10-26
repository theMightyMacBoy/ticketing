<?php

use Coderflex\LaravelTicket\Demo\DeskDemoSeeder;
use Coderflex\LaravelTicket\Desk\TicketDesk;
use Coderflex\LaravelTicket\Http\Controllers\LoginController;
use Coderflex\LaravelTicket\Http\DeskAuthRoutes;
use Coderflex\LaravelTicket\Mail\GraphMailboxReader;
use Coderflex\LaravelTicket\Mail\MailboxReader;
use Coderflex\LaravelTicket\Mail\TicketNoticeMail;
use Coderflex\LaravelTicket\Models\AssignmentGroup;
use Coderflex\LaravelTicket\Models\Department;
use Coderflex\LaravelTicket\Models\Mailbox;
use Coderflex\LaravelTicket\Models\Message;
use Coderflex\LaravelTicket\Models\Service;
use Coderflex\LaravelTicket\Models\StaffProfile;
use Coderflex\LaravelTicket\Models\Ticket;
use Coderflex\LaravelTicket\Tests\Models\User;
use Coderflex\LaravelTicket\Tests\Support\FakeMailboxReader;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

function deskWithoutCsrf(): void
{
    $middleware = array_values(array_filter([
        class_exists(ValidateCsrfToken::class) ? ValidateCsrfToken::class : null,
        class_exists(VerifyCsrfToken::class) ? VerifyCsrfToken::class : null,
    ]));

    test()->withoutMiddleware($middleware);
}

function demoDesk(): void
{
    app(DeskDemoSeeder::class)->seedSamples();
}

it('seeds the demo organization without creating users', function () {
    config()->set('laravel_ticket.demo.seed', false);
    expect(Department::query()->count())->toBe(0);

    config()->set('laravel_ticket.demo.seed', true);
    $source = (string) file_get_contents(__DIR__.'/../../database/migrations/seed_demo_desk.php.stub');
    $source = (string) preg_replace('/^\s*<\?php/', '', $source);
    $migration = eval($source);
    $migration->up();

    expect(Department::query()->where('slug', 'hr')->exists())->toBeTrue()
        ->and(Department::query()->where('slug', 'facilities')->exists())->toBeTrue()
        ->and(Mailbox::query()->where('address', 'help@example.com')->first()->is_active)->toBeFalse()
        ->and(User::query()->count())->toBe(0);
});

it('loads demo people and sample tickets from the command', function () {
    $this->artisan('tickets:seed-demo')->assertSuccessful();

    $payroll = Ticket::query()->where('title', 'Payroll question')->first();
    $vpn = Ticket::query()->where('title', 'VPN is down for Ada')->first();

    expect($payroll)->not->toBeNull()
        ->and($payroll->is_confidential)->toBeTrue()
        ->and($payroll->ticket_number)->toBe('CASE-000001')
        ->and($vpn->ticket_number)->toBe('INC-000001')
        ->and(Ticket::query()->where('title', 'Badge for the west door')->value('type'))->toBe('work_order')
        ->and(StaffProfile::query()->count())->toBe(5)
        ->and(User::query()->where('email', 'ada@example.com')->exists())->toBeTrue();

    $this->artisan('tickets:seed-demo')->assertSuccessful();

    expect(Ticket::query()->count())->toBe(3);
});

it('hides confidential tickets from a platform admin', function () {
    demoDesk();
    deskWithoutCsrf();

    $payroll = Ticket::query()->where('title', 'Payroll question')->firstOrFail();
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    config()->set('laravel_ticket.agents.callback', fn ($user) => $user->email === 'admin@example.com');

    expect(Gate::forUser($admin)->denies('view', $payroll))->toBeTrue();

    $this->actingAs($admin)
        ->get(route('laravel-ticket.tickets.show', $payroll))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('laravel-ticket.desk', ['q' => 'Payroll']))
        ->assertOk()
        ->assertDontSee('Payroll question');

    $this->actingAs($admin)
        ->get(route('laravel-ticket.desk'))
        ->assertOk()
        ->assertSee('VPN is down for Ada');
});

it('lets the department agent open a confidential ticket and the requester use the portal', function () {
    demoDesk();
    deskWithoutCsrf();

    $payroll = Ticket::query()->where('title', 'Payroll question')->firstOrFail();
    $hr = User::query()->where('email', 'hr.agent@example.com')->firstOrFail();
    $ada = User::query()->where('email', 'ada@example.com')->firstOrFail();

    $this->actingAs($hr)
        ->get(route('laravel-ticket.tickets.show', $payroll))
        ->assertOk()
        ->assertSee('Payroll question');

    $this->actingAs($hr)
        ->post(route('laravel-ticket.tickets.note', $payroll), ['message' => 'Keep this inside HR'])
        ->assertRedirect();

    $this->actingAs($ada)
        ->get(route('laravel-ticket.portal'))
        ->assertOk()
        ->assertSee('Payroll question')
        ->assertSee('VPN is down for Ada');

    $this->actingAs($ada)
        ->get(route('laravel-ticket.portal.tickets.show', $payroll))
        ->assertOk()
        ->assertDontSee('Keep this inside HR');

    $this->actingAs($ada)
        ->post(route('laravel-ticket.portal.tickets.reply', $payroll), ['message' => 'It was the bonus line'])
        ->assertRedirect();

    expect(Message::query()->where('message', 'It was the bonus line')->exists())->toBeTrue();
});

it('opens a catalog request and finds it by search', function () {
    demoDesk();
    deskWithoutCsrf();

    $ada = User::query()->where('email', 'ada@example.com')->firstOrFail();
    $vpn = Service::query()->where('slug', 'vpn')->firstOrFail();
    $agent = User::query()->where('email', 'it.agent@example.com')->firstOrFail();

    $this->actingAs($ada)
        ->post(route('laravel-ticket.catalog.store'), [
            'service_id' => $vpn->id,
            'title' => 'Need a laptop VPN profile',
            'message' => 'Traveling next week',
            'fields' => ['hostname' => 'ada-laptop'],
        ])
        ->assertRedirect();

    $ticket = Ticket::query()->where('title', 'Need a laptop VPN profile')->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->custom_attributes['hostname'] ?? null)->toBe('ada-laptop')
        ->and($ticket->assignment_group_id)->toBe(AssignmentGroup::query()->where('slug', 'it-network')->value('id'));

    $this->actingAs($agent)
        ->get(route('laravel-ticket.desk', ['q' => 'laptop VPN']))
        ->assertOk()
        ->assertSee('Need a laptop VPN profile');
});

it('acknowledges a new ticket by email', function () {
    config()->set('laravel_ticket.notifications.mail', true);
    Mail::fake();

    $requester = User::factory()->create(['email' => 'ada@example.com']);
    $ticket = app(TicketDesk::class)->open([
        'title' => 'Badge printer',
        'requester_email' => 'ada@example.com',
        'user_id' => $requester->id,
    ], $requester);

    Mail::assertSent(TicketNoticeMail::class, function (TicketNoticeMail $mail) use ($ticket) {
        return str_contains($mail->bodyText, (string) $ticket->ticket_number)
            && $mail->hasTo('ada@example.com');
    });
});

it('erases a requester and leaves another department confidential ticket for a platform admin', function () {
    demoDesk();
    deskWithoutCsrf();

    $payroll = Ticket::query()->where('title', 'Payroll question')->firstOrFail();
    $payroll->messageAsUser(User::query()->where('email', 'ada@example.com')->first(), 'Bonus details');
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    config()->set('laravel_ticket.agents.callback', fn ($user) => $user->email === 'admin@example.com');

    $this->actingAs($admin)
        ->post(route('laravel-ticket.privacy.erase'), ['email' => 'ada@example.com'])
        ->assertRedirect();

    expect($payroll->fresh()->title)->toBe('Payroll question')
        ->and($payroll->fresh()->message)->toBe('Ada asked about a missing payroll line.');

    $ada = User::query()->where('email', 'ada@example.com')->firstOrFail();

    $this->actingAs($ada)
        ->post(route('laravel-ticket.privacy.erase'), ['email' => 'ada@example.com'])
        ->assertRedirect();

    expect($payroll->fresh()->title)->toBe('Erased request')
        ->and($payroll->fresh()->message)->toBeNull()
        ->and($payroll->fresh()->requester_email)->toBeNull()
        ->and(Message::query()->where('ticket_id', $payroll->id)->value('message'))->toBe('[erased]')
        ->and($payroll->fresh()->ticket_number)->toBe('CASE-000001');
});

it('records a mailbox failure and pages the alert address', function () {
    config()->set('laravel_ticket.notifications.mail', true);
    config()->set('laravel_ticket.mail.alert_to', 'oncall@example.com');
    Mail::fake();

    $reader = new class extends FakeMailboxReader
    {
        public function fetch(Mailbox $mailbox): array
        {
            throw new RuntimeException('inbox down');
        }
    };
    app()->instance(MailboxReader::class, $reader);

    $mailbox = Mailbox::query()->create([
        'name' => 'Help',
        'address' => 'help@contoso.com',
        'tenant_id' => 'tenant',
        'client_id' => 'client',
        'client_secret' => 'secret-1',
        'is_active' => true,
    ]);

    $this->artisan('tickets:fetch-mail')->assertFailed();

    expect($mailbox->fresh()->last_error)->toBe('inbox down');

    Mail::assertSent(TicketNoticeMail::class, function (TicketNoticeMail $mail) {
        return $mail->hasTo('oncall@example.com')
            && $mail->bodyText === 'inbox down'
            && ! str_contains($mail->bodyText, 'secret-1');
    });
});

it('follows the next page of unread graph mail', function () {
    $mailbox = Mailbox::query()->create([
        'name' => 'Help',
        'address' => 'help@contoso.com',
        'tenant_id' => 'tenant',
        'client_id' => 'client',
        'client_secret' => 'secret-1',
    ]);

    $page = function (string $id, bool $read = false, string $at = '2026-10-25T16:00:00Z'): array {
        return [
            'id' => $id,
            'subject' => $id,
            'body' => ['contentType' => 'text', 'content' => $id],
            'from' => ['emailAddress' => ['address' => 'ada@contoso.com', 'name' => 'Ada']],
            'receivedDateTime' => $at,
            'isRead' => $read,
        ];
    };

    Http::fake(function ($request) use ($page) {
        $url = urldecode($request->url());

        if (str_contains($url, 'login.microsoftonline.com')) {
            return Http::response(['access_token' => 'token', 'expires_in' => 3600]);
        }

        if (str_contains($url, 'skip=1')) {
            return Http::response(['value' => [$page('graph-b', false, '2026-10-25T17:00:00Z')]]);
        }

        if (str_contains($url, 'isRead eq false')) {
            return Http::response([
                'value' => [$page('graph-a')],
                '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/users/help@contoso.com/mailFolders/inbox/messages?skip=1',
            ]);
        }

        return Http::response(['value' => []], 200);
    });

    $fetched = app(GraphMailboxReader::class)->fetch($mailbox);

    expect(collect($fetched)->map->graphId->all())->toBe(['graph-a', 'graph-b']);
});

it('retries the inbox without the unread filter when graph rejects it', function () {
    $mailbox = Mailbox::query()->create([
        'name' => 'Help',
        'address' => 'help@contoso.com',
        'tenant_id' => 'tenant',
        'client_id' => 'client',
        'client_secret' => 'secret-1',
    ]);

    $page = function (string $id, bool $read = false): array {
        return [
            'id' => $id,
            'subject' => $id,
            'body' => ['contentType' => 'text', 'content' => $id],
            'from' => ['emailAddress' => ['address' => 'ada@contoso.com', 'name' => 'Ada']],
            'receivedDateTime' => '2026-10-25T16:00:00Z',
            'isRead' => $read,
        ];
    };

    Http::fake(function ($request) use ($page) {
        $url = urldecode($request->url());

        if (str_contains($url, 'login.microsoftonline.com')) {
            return Http::response(['access_token' => 'token', 'expires_in' => 3600]);
        }

        if (str_contains($url, 'isRead eq false')) {
            return Http::response(['error' => ['code' => 'BadRequest']], 400);
        }

        return Http::response([
            'value' => [$page('read-1', true), $page('unread-1')],
        ]);
    });

    $retried = app(GraphMailboxReader::class)->fetch($mailbox);

    expect(collect($retried)->map->graphId->all())->toBe(['unread-1']);
});

it('lets an agent maintain setup and the directory', function () {
    demoDesk();
    deskWithoutCsrf();

    $stranger = User::factory()->create();
    $agent = User::query()->where('email', 'it.agent@example.com')->firstOrFail();

    $this->actingAs($stranger)->get(route('laravel-ticket.setup'))->assertForbidden();

    $this->actingAs($agent)
        ->get(route('laravel-ticket.setup'))
        ->assertOk()
        ->assertSee('help@example.com');

    $it = Department::query()->where('slug', 'it')->firstOrFail();

    $this->actingAs($agent)
        ->post(route('laravel-ticket.directory.store'), [
            'name' => 'New Tech',
            'email' => 'tech@example.com',
            'department_id' => $it->id,
            'employee_number' => 'E900',
            'is_agent' => '1',
        ])
        ->assertRedirect();

    expect(StaffProfile::query()->where('email', 'tech@example.com')->first()->is_agent)->toBeTrue()
        ->and(Department::agentDepartmentIds(User::query()->where('email', 'tech@example.com')->first()))->toBe([$it->id]);
});

it('signs in a demo user when the login screen is mounted', function () {
    Route::middleware('web')->group(function () {
        Route::get('/desk/login', [LoginController::class, 'create'])->name('laravel-ticket.login');
        Route::post('/desk/login', [LoginController::class, 'store'])->name('laravel-ticket.login.store');
    });

    deskWithoutCsrf();

    User::factory()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->post('/desk/login', [
        'email' => 'ada@example.com',
        'password' => 'password',
    ])->assertRedirect(route('laravel-ticket.desk'));

    $this->get(route('laravel-ticket.desk'))->assertOk();
});

it('renders the desk, portal, and sign-in screens as one console', function () {
    demoDesk();
    deskWithoutCsrf();
    DeskAuthRoutes::register('desk');

    $agent = User::query()->where('email', 'it.agent@example.com')->firstOrFail();
    $ada = User::query()->where('email', 'ada@example.com')->firstOrFail();
    $vpn = Ticket::query()->where('title', 'VPN is down for Ada')->firstOrFail();
    $service = Service::query()->where('slug', 'vpn')->firstOrFail();

    $this->actingAs($agent)
        ->get(route('laravel-ticket.desk'))
        ->assertOk()
        ->assertSee('class="shell"', false)
        ->assertSee('Open work')
        ->assertSee('VPN is down for Ada')
        ->assertSee('Administration')
        ->assertSee('it.agent@example.com')
        ->assertSee('data-theme-toggle', false)
        ->assertSee('Dark mode')
        ->assertDontSee('Georgia', false);

    $this->actingAs($agent)
        ->get(route('laravel-ticket.tickets.show', $vpn))
        ->assertOk()
        ->assertSee('INC-000001')
        ->assertSee('Public reply')
        ->assertSee('Internal note')
        ->assertSee('Ada cannot reach the corporate VPN.');

    $this->actingAs($agent)
        ->get(route('laravel-ticket.setup'))
        ->assertOk()
        ->assertSee('Incident')
        ->assertSee('help@example.com')
        ->assertSee('Single sign-on');

    $this->actingAs($ada)
        ->get(route('laravel-ticket.portal'))
        ->assertOk()
        ->assertSee('My tickets')
        ->assertSee('Payroll question')
        ->assertDontSee('Administration');

    $this->actingAs($ada)
        ->get(route('laravel-ticket.catalog', ['service' => $service->id]))
        ->assertOk()
        ->assertSee('Hostname')
        ->assertSee('Open ticket');

    $this->get(route('laravel-ticket.login'))
        ->assertOk()
        ->assertSee('class="gate-split"', false)
        ->assertSee('Company addresses continue at the identity provider')
        ->assertSee('Dark mode')
        ->assertDontSee('name="password"', false);

    $this->withSession([LoginController::EMAIL => 'ada@example.com'])
        ->get(route('laravel-ticket.login.password'))
        ->assertOk()
        ->assertSee('Enter the password for ada@example.com')
        ->assertSee('name="password"', false);
});
