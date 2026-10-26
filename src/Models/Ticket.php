<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns;
use Coderflex\LaravelTicket\Scopes\TicketScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Coderflex\LaravelTicket\Models\Ticket
 *
 * @property string|null $uuid
 * @property string|null $ticket_number
 * @property int $user_id
 * @property string $title
 * @property string|null $message
 * @property string $priority
 * @property string $status
 * @property string $type
 * @property string $impact
 * @property string $urgency
 * @property string $channel
 * @property string|null $pending_reason
 * @property bool $is_resolved
 * @property bool $is_locked
 * @property bool $is_major
 * @property int|null $assigned_to
 * @property int|null $organization_id
 * @property int|null $affected_user_id
 * @property int|null $assignment_group_id
 * @property int|null $department_id
 * @property int|null $service_id
 * @property int|null $resolution_code_id
 * @property int|null $sla_policy_id
 * @property Carbon|null $response_due_at
 * @property Carbon|null $resolution_due_at
 * @property Carbon|null $first_response_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $sla_paused_at
 * @property int $sla_paused_minutes
 * @property bool $response_breached
 * @property bool $resolution_breached
 * @property array<string, mixed>|null $custom_attributes
 * @property int|null $mailbox_id
 * @property string|null $requester_email
 * @property bool $is_confidential
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Ticket extends Model
{
    use Concerns\InteractsWithItsm;
    use Concerns\InteractsWithTicketRelations;
    use Concerns\InteractsWithTickets;
    use HasFactory;
    use TicketScope;

    /**
     * Original attribute values captured during updating for the audit log.
     * Declared so it is not written through Eloquent's attribute bag.
     *
     * @var array<string, mixed>
     */
    public array $auditFrom = [];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'message',
        'priority',
        'status',
        'type',
        'impact',
        'urgency',
        'channel',
        'pending_reason',
        'is_resolved',
        'is_locked',
        'is_major',
        'assigned_to',
        'organization_id',
        'affected_user_id',
        'assignment_group_id',
        'department_id',
        'service_id',
        'resolution_code_id',
        'mailbox_id',
        'requester_email',
        'is_confidential',
        'custom_attributes',
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'is_locked' => 'boolean',
        'is_major' => 'boolean',
        'is_confidential' => 'boolean',
        'custom_attributes' => 'array',
        'response_due_at' => 'datetime',
        'resolution_due_at' => 'datetime',
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'sla_paused_at' => 'datetime',
        'response_breached' => 'boolean',
        'resolution_breached' => 'boolean',
        'sla_paused_minutes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'assigned_to');
    }

    public function affectedUser(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'affected_user_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.organization'));
    }

    public function assignmentGroup(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.assignment_group'));
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.department'));
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.mailbox'));
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.service'));
    }

    public function resolutionCode(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.resolution_code'));
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.sla_policy'));
    }

    public function messages(): HasMany
    {
        $tableName = config('laravel_ticket.table_names.messages', 'messages');

        return $this->hasMany(
            config('laravel_ticket.models.message'),
            (string) $tableName['columns']['ticket_foreign_id'],
        );
    }

    public function categories(): BelongsToMany
    {
        $table = config('laravel_ticket.table_names.category_ticket', 'category_ticket');

        return $this->belongsToMany(
            config('laravel_ticket.models.category'),
            $table['table'],
            $table['columns']['ticket_foreign_id'],
            $table['columns']['category_foreign_id'],
        );
    }

    public function labels(): BelongsToMany
    {
        $table = config('laravel_ticket.table_names.label_ticket', 'label_ticket');

        return $this->belongsToMany(
            config('laravel_ticket.models.label'),
            $table['table'],
            $table['columns']['ticket_foreign_id'],
            $table['columns']['label_foreign_id'],
        );
    }

    public function activities(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.ticket_activity'));
    }

    public function links(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.ticket_link'));
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.approval'));
    }

    public function workLogs(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.work_log'));
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(config('laravel_ticket.models.attachment'));
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(
            config('auth.providers.users.model'),
            config('laravel_ticket.table_names.ticket_watchers', 'ticket_watchers')
        )->withTimestamps();
    }

    public function configurationItems(): BelongsToMany
    {
        return $this->belongsToMany(
            config('laravel_ticket.models.configuration_item'),
            config('laravel_ticket.table_names.configuration_item_ticket', 'configuration_item_ticket')
        )->withTimestamps();
    }

    public function getTable()
    {
        return config(
            'laravel_ticket.table_names.tickets',
            parent::getTable()
        );
    }
}
