<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Enums\MessageVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Coderflex\LaravelTicket\Models\Message
 *
 * @property int $user_id
 * @property string $message
 * @property string $visibility
 * @property Carbon|null $created_at
 * @property-read Ticket|null $ticket
 */
class Message extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'ticket_id',
        'message',
        'visibility',
    ];

    /**
     * Get Ticket RelationShip
     */
    public function ticket(): BelongsTo
    {
        $tableName = config('laravel_ticket.table_names.messages', 'messages');

        return $this->belongsTo(
            config('laravel_ticket.models.ticket'),
            $tableName['columns']['ticket_foreign_id']
        );
    }

    /**
     * Get Message Relationship
     */
    public function user(): BelongsTo
    {
        $tableName = config('laravel_ticket.table_names.messages', [
            'columns' => ['user_foreign_id' => 'user_id'],
        ]);

        return $this->belongsTo(
            config('auth.providers.users.model'),
            $tableName['columns']['user_foreign_id']
        );
    }

    public function scopePublic(Builder $builder): Builder
    {
        return $builder->where('visibility', MessageVisibility::PUBLIC->value);
    }

    public function scopeInternal(Builder $builder): Builder
    {
        return $builder->where('visibility', MessageVisibility::INTERNAL->value);
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config(
            'laravel_ticket.table_names.messages.table',
            parent::getTable()
        );
    }
}
