<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $department_id
 * @property string|null $ticket_type
 * @property int $response_minutes
 * @property int $resolution_minutes
 * @property bool $use_business_hours
 */
class SlaPolicy extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'name',
        'department_id',
        'priority',
        'ticket_type',
        'response_minutes',
        'resolution_minutes',
        'use_business_hours',
        'is_default',
    ];

    protected $casts = [
        'response_minutes' => 'integer',
        'resolution_minutes' => 'integer',
        'use_business_hours' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.department'));
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.ticket'));
    }

    protected function configuredTable(): string
    {
        return 'sla_policies';
    }
}
