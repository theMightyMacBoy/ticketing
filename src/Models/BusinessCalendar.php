<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $department_id
 * @property string $name
 * @property string $timezone
 * @property array<string, mixed> $week
 * @property array<int, string>|null $holidays
 */
class BusinessCalendar extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'department_id',
        'name',
        'timezone',
        'week',
        'holidays',
    ];

    protected $casts = [
        'week' => 'array',
        'holidays' => 'array',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.department'));
    }

    /**
     * @return array{timezone: string, week: array<string, mixed>, holidays: array<int, string>}
     */
    public function toSchedule(): array
    {
        return [
            'timezone' => $this->timezone,
            'week' => $this->week,
            'holidays' => $this->holidays ?? [],
        ];
    }

    protected function configuredTable(): string
    {
        return 'business_calendars';
    }
}
