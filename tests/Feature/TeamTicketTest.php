<?php

use Coderflex\LaravelTicket\Models\AssignmentGroup;
use Coderflex\LaravelTicket\Models\AuthIdentity;
use Coderflex\LaravelTicket\Models\Department;
use Coderflex\LaravelTicket\Models\Ticket;
use Coderflex\LaravelTicket\Tests\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

it('lets a manager open the tickets filed by direct reports', function () {
    $manager = User::factory()->create(['email' => 'lead@contoso.com', 'name' => 'Lead']);
    $report = User::factory()->create(['email' => 'ada@contoso.com', 'name' => 'Ada']);
    $other = User::factory()->create(['email' => 'other@contoso.com']);
    $admin = User::factory()->create(['email' => 'admin@example.com']);

    AuthIdentity::query()->create([
        'email' => 'ada@contoso.com',
        'method' => 'sso',
        'subject' => 'ada',
        'manager_email' => 'lead@contoso.com',
    ]);

    $open = Ticket::factory()->create([
        'user_id' => $report->getKey(),
        'title' => 'VPN is down for Ada',
        'status' => 'open',
        'requester_email' => 'ada@contoso.com',
        'is_confidential' => false,
    ]);
    $payroll = Ticket::factory()->create([
        'user_id' => $report->getKey(),
        'title' => 'Payroll for Ada',
        'status' => 'open',
        'requester_email' => 'ada@contoso.com',
        'is_confidential' => true,
    ]);
    Ticket::factory()->create([
        'user_id' => $report->getKey(),
        'title' => 'Old laptop request',
        'status' => 'closed',
        'requester_email' => 'ada@contoso.com',
        'is_confidential' => false,
    ]);
    Ticket::factory()->create([
        'user_id' => $other->getKey(),
        'title' => 'Unrelated badge',
        'status' => 'open',
        'requester_email' => 'other@contoso.com',
    ]);

    $this->actingAs($manager)
        ->get(route('laravel-ticket.desk'))
        ->assertOk()
        ->assertDontSee('Payroll for Ada')
        ->assertDontSee('VPN is down for Ada');

    $this->actingAs($manager)
        ->get(route('laravel-ticket.team'))
        ->assertOk()
        ->assertSee('Tickets opened by my team')
        ->assertSee('VPN is down for Ada')
        ->assertSee('Payroll for Ada')
        ->assertSee('Old laptop request')
        ->assertSee('Confidential')
        ->assertDontSee('Unrelated badge');

    $this->actingAs($manager)
        ->get(route('laravel-ticket.team.open', ['q' => 'Payroll']))
        ->assertOk()
        ->assertSee('Open tickets by my team')
        ->assertSee('Payroll for Ada')
        ->assertDontSee('VPN is down for Ada')
        ->assertDontSee('Old laptop request');

    $this->actingAs($manager)
        ->get(route('laravel-ticket.tickets.show', $open))
        ->assertOk()
        ->assertSee('VPN is down for Ada');

    $this->actingAs($manager)
        ->get(route('laravel-ticket.tickets.show', $payroll))
        ->assertOk()
        ->assertSee('Payroll for Ada');

    $this->actingAs($other)
        ->get(route('laravel-ticket.tickets.show', $payroll))
        ->assertForbidden();

    $this->actingAs($other)
        ->get(route('laravel-ticket.team'))
        ->assertOk()
        ->assertSee('No one is recorded as reporting to you')
        ->assertDontSee('Payroll for Ada');

    config()->set('laravel_ticket.agents.callback', fn ($user) => $user->email === 'admin@example.com');

    $this->actingAs($admin)
        ->get(route('laravel-ticket.tickets.show', $payroll))
        ->assertForbidden();
});

it('hides a support group from the requester manager', function () {
    $middleware = array_values(array_filter([
        class_exists(ValidateCsrfToken::class) ? ValidateCsrfToken::class : null,
        class_exists(VerifyCsrfToken::class) ? VerifyCsrfToken::class : null,
    ]));
    $this->withoutMiddleware($middleware);

    $manager = User::factory()->create(['email' => 'lead@contoso.com', 'name' => 'Lead']);
    $report = User::factory()->create(['email' => 'ada@contoso.com', 'name' => 'Ada']);
    $hr = Department::query()->create(['name' => 'HR', 'slug' => 'hr', 'is_active' => true]);
    $group = AssignmentGroup::query()->create([
        'name' => 'HR Employee Relations',
        'slug' => 'hr-employee-relations',
        'department_id' => $hr->getKey(),
        'is_active' => true,
        'hide_from_managers' => true,
    ]);

    AuthIdentity::query()->create([
        'email' => 'ada@contoso.com',
        'method' => 'sso',
        'subject' => 'ada',
        'manager_email' => 'lead@contoso.com',
    ]);

    $payroll = Ticket::factory()->create([
        'user_id' => $report->getKey(),
        'title' => 'Payroll for Ada',
        'status' => 'open',
        'requester_email' => 'ada@contoso.com',
        'department_id' => $hr->getKey(),
        'assignment_group_id' => $group->getKey(),
        'is_confidential' => true,
    ]);
    Ticket::factory()->create([
        'user_id' => $report->getKey(),
        'title' => 'VPN is down for Ada',
        'status' => 'open',
        'requester_email' => 'ada@contoso.com',
        'is_confidential' => false,
    ]);

    $this->actingAs($manager)
        ->get(route('laravel-ticket.team'))
        ->assertOk()
        ->assertSee('VPN is down for Ada')
        ->assertDontSee('Payroll for Ada');

    $this->actingAs($manager)
        ->get(route('laravel-ticket.team.open'))
        ->assertOk()
        ->assertDontSee('Payroll for Ada');

    $this->actingAs($manager)
        ->get(route('laravel-ticket.tickets.show', $payroll))
        ->assertForbidden();

    $hr->addAgent($manager);

    $this->actingAs($manager)
        ->get(route('laravel-ticket.team'))
        ->assertDontSee('Payroll for Ada');

    $this->actingAs($manager)
        ->get(route('laravel-ticket.tickets.show', $payroll))
        ->assertOk()
        ->assertSee('Payroll for Ada');

    config()->set('laravel_ticket.agents.callback', fn () => true);
    $agent = User::factory()->create(['email' => 'it.agent@example.com']);

    $this->actingAs($agent)
        ->post(route('laravel-ticket.setup.groups.managers', $group))
        ->assertRedirect();

    expect($group->fresh()->hide_from_managers)->toBeFalse();

    $this->actingAs($manager)
        ->get(route('laravel-ticket.team'))
        ->assertSee('Payroll for Ada');

    $this->actingAs($agent)
        ->post(route('laravel-ticket.setup.groups'), [
            'name' => 'HR Benefits',
            'department_id' => $hr->getKey(),
            'hide_from_managers' => '1',
        ])
        ->assertRedirect();

    $benefits = AssignmentGroup::query()->where('slug', 'hr-benefits')->first();

    expect($benefits)->not->toBeNull()
        ->and($benefits->hide_from_managers)->toBeTrue();

    $this->actingAs($agent)
        ->get(route('laravel-ticket.setup'))
        ->assertSee('Block the requester\'s manager', false)
        ->assertSee('HR Employee Relations');
});
