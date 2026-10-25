<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketActivity extends Model
{
    use UsesConfiguredTable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'event',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.ticket'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    protected function configuredTable(): string
    {
        return 'ticket_activities';
    }
}
