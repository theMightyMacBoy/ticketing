<?php

namespace Coderflex\LaravelTicket\Policies;

use Coderflex\LaravelTicket\Contracts\CanManageTickets;
use Coderflex\LaravelTicket\Enums\ApprovalStatus;
use Coderflex\LaravelTicket\Models\Ticket;
use Illuminate\Database\Eloquent\Model;

class TicketPolicy
{
    public function view(Model $user, Ticket $ticket): bool
    {
        if ($this->manages($user)) {
            return true;
        }

        $id = (int) $user->getKey();
        $involved = array_filter([
            (int) $ticket->user_id,
            (int) $ticket->affected_user_id,
            (int) $ticket->assigned_to,
        ]);

        if (in_array($id, $involved, true)) {
            return true;
        }

        if ($ticket->watchers()->whereKey($id)->exists()) {
            return true;
        }

        return $this->inGroup($user, $ticket);
    }

    public function update(Model $user, Ticket $ticket): bool
    {
        if ($this->manages($user)) {
            return true;
        }

        if ((int) $ticket->assigned_to === (int) $user->getKey()) {
            return true;
        }

        return $this->inGroup($user, $ticket);
    }

    public function addInternalNote(Model $user, Ticket $ticket): bool
    {
        return $this->update($user, $ticket);
    }

    public function delete(Model $user, Ticket $ticket): bool
    {
        return $this->manages($user);
    }

    public function approve(Model $user, Ticket $ticket): bool
    {
        if ($this->manages($user)) {
            return true;
        }

        return $ticket->approvals()
            ->where('approver_id', $user->getKey())
            ->where('status', ApprovalStatus::PENDING->value)
            ->exists();
    }

    private function manages(Model $user): bool
    {
        $callback = config('laravel_ticket.agents.callback');

        if (is_callable($callback) && $callback($user)) {
            return true;
        }

        return $user instanceof CanManageTickets && $user->canManageTickets();
    }

    private function inGroup(Model $user, Ticket $ticket): bool
    {
        if (! $ticket->assignment_group_id) {
            return false;
        }

        return $ticket->assignmentGroup()
            ->whereHas('members', fn ($query) => $query->whereKey($user->getKey()))
            ->exists();
    }
}
