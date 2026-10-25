<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $current
 */
class TicketSequence extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'prefix',
        'current',
    ];

    protected $casts = [
        'current' => 'integer',
    ];

    protected function configuredTable(): string
    {
        return 'ticket_sequences';
    }
}
