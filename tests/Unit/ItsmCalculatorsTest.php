<?php

use Carbon\Carbon;
use Coderflex\LaravelTicket\Sla\BusinessHours;
use Coderflex\LaravelTicket\Sla\PriorityMatrix;

it('scores priority from impact and urgency', function () {
    $matrix = new PriorityMatrix;

    expect($matrix->calculate('low', 'low'))->toBe('low')
        ->and($matrix->calculate('low', 'high'))->toBe('medium')
        ->and($matrix->calculate('high', 'medium'))->toBe('high')
        ->and($matrix->calculate('high', 'critical'))->toBe('critical')
        ->and($matrix->calculate('critical', 'critical'))->toBe('critical')
        ->and($matrix->calculate('normal', 'normal'))->toBe('medium');
});

it('rolls minutes onto the next working day and skips holidays', function () {
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

    $hours = new BusinessHours;
    $start = Carbon::parse('2026-06-05 16:00:00', 'UTC');

    expect($hours->addMinutes($start, 120)->format('Y-m-d H:i'))->toBe('2026-06-08 10:00')
        ->and($hours->diffMinutes($start, Carbon::parse('2026-06-08 10:00:00', 'UTC')))->toBe(120);

    config()->set('laravel_ticket.business_hours.holidays', ['2026-06-08']);

    expect($hours->addMinutes($start, 120)->format('Y-m-d H:i'))->toBe('2026-06-09 10:00');
});
