<?php

namespace Coderflex\LaravelTicket\Models;

use Coderflex\LaravelTicket\Concerns\UsesConfiguredTable;
use Coderflex\LaravelTicket\Enums\ApprovalStatus;
use Coderflex\LaravelTicket\Events\ApprovalDecided;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    use UsesConfiguredTable;

    protected $fillable = [
        'ticket_id',
        'approver_id',
        'requested_by',
        'status',
        'comment',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
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
        $this->update([
            'status' => $status,
            'comment' => $comment,
            'decided_at' => now(),
        ]);

        event(new ApprovalDecided($this));

        return $this;
    }

    protected function configuredTable(): string
    {
        return 'approvals';
    }
}
