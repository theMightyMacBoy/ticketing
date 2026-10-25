<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketLink extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'ticket_id',
        'linked_ticket_id',
        'type',
        'user_id',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.ticket'));
    }

    public function linkedTicket(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.ticket'), 'linked_ticket_id');
    }

    protected function configuredTable(): string
    {
        return 'ticket_links';
    }
}
