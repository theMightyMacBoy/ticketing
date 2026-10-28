<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Enums\MessageVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Coderflex\LaravelTicket\Models\Message
 *
 * @property int $user_id
 * @property string $message
 * @property string $format
 * @property string $visibility
 * @property string|null $sender_email
 * @property string|null $sender_name
 * @property Carbon|null $created_at
 * @property-read Ticket|null $ticket
 */
class Message extends Model
{
    use HasFactory;

    /**
     * Inbound mail sets this so the customer's own message is not relayed back out.
     * The desk composer sets $holdMail so files can be linked before the reply is sent.
     */
    public bool $suppressOutboundMail = false;

    public static bool $holdMail = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'ticket_id',
        'message',
        'format',
        'visibility',
        'sender_email',
        'sender_name',
    ];

    protected $attributes = [
        'format' => 'plain',
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

    public function attachments(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.attachment'));
    }

    public function displayName(): string
    {
        if (is_string($this->sender_name) && $this->sender_name !== '') {
            return $this->sender_name;
        }

        $user = $this->relationLoaded('user') ? $this->getRelation('user') : $this->user()->first();

        if ($user instanceof Model) {
            $name = $user->getAttribute('name');

            if (is_string($name) && $name !== '') {
                return $name;
            }

            $email = $user->getAttribute('email');

            if (is_string($email) && $email !== '') {
                return $email;
            }
        }

        if (is_string($this->sender_email) && $this->sender_email !== '') {
            return $this->sender_email;
        }

        return 'Agent';
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
