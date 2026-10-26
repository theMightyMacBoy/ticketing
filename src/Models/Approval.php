<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Coderflex\LaravelTicket\Enums\ApprovalStatus;
use Coderflex\LaravelTicket\Events\ApprovalDecided;
use Coderflex\LaravelTicket\Events\ApprovalRequested;
use Coderflex\LaravelTicket\Exceptions\ApprovalRequired;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $ticket_id
 * @property int $position
 * @property string $status
 */
class Approval extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'ticket_id',
        'approver_id',
        'requested_by',
        'status',
        'position',
        'comment',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
        'position' => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(config('laravel_ticket.models.ticket'));
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'approver_id');
    }

    public function approve(?string $comment = null): self
    {
        return $this->decide(ApprovalStatus::APPROVED->value, $comment);
    }

    public function reject(?string $comment = null): self
    {
        return $this->decide(ApprovalStatus::REJECTED->value, $comment);
    }

    public function decide(string $status, ?string $comment = null): self
    {
        if ((string) $this->status !== ApprovalStatus::PENDING->value) {
            throw new ApprovalRequired('This approval is not the active step.');
        }

        $this->update([
            'status' => $status,
            'comment' => $comment,
            'decided_at' => now(),
        ]);

        event(new ApprovalDecided($this));

        if ($status === ApprovalStatus::APPROVED->value) {
            $this->activateNext();
        }

        return $this;
    }

    private function activateNext(): void
    {
        $next = static::query()
            ->where('ticket_id', $this->ticket_id)
            ->where('status', ApprovalStatus::WAITING->value)
            ->orderBy('position')
            ->first();

        if (! $next instanceof self) {
            return;
        }

        $next->update(['status' => ApprovalStatus::PENDING->value]);

        event(new ApprovalRequested($next));
    }

    protected function configuredTable(): string
    {
        return 'approvals';
    }
}
