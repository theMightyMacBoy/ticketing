<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $mailbox_id
 * @property int $ticket_id
 * @property int|null $message_id
 * @property string $direction
 * @property string|null $graph_id
 * @property string|null $internet_message_id
 * @property string|null $in_reply_to
 * @property string|null $from_address
 * @property string|null $subject
 * @property Carbon|null $received_at
 */
class EmailMessage extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'mailbox_id',
        'ticket_id',
        'message_id',
        'direction',
        'graph_id',
        'internet_message_id',
        'in_reply_to',
        'from_address',
        'subject',
        'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.mailbox'));
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.ticket'));
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.message'));
    }

    protected function configuredTable(): string
    {
        return 'email_messages';
    }
}
