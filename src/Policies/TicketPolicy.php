<?php

namespace Coderflex\LaravelTicket\Policies;

use Coderflex\LaravelTicket\Access\TicketVisibility;
use Coderflex\LaravelTicket\Enums\ApprovalStatus;
use Coderflex\LaravelTicket\Models\Department;
use Coderflex\LaravelTicket\Models\Ticket;
use Illuminate\Database\Eloquent\Model;

class TicketPolicy
{
    public function view(Model $user, Ticket $ticket): bool
    {
        if ($this->blocked($user, $ticket)) {
            return false;
        }

        return TicketVisibility::manages($user) || TicketVisibility::engaged($user, $ticket);
    }

    public function update(Model $user, Ticket $ticket): bool
    {
        if ($this->blocked($user, $ticket)) {
            return false;
        }

        if (TicketVisibility::manages($user)) {
            return true;
        }

        if ((int) $ticket->assigned_to === (int) $user->getKey()) {
            return true;
        }

        return $this->inGroup($user, $ticket) || $this->inDepartment($user, $ticket);
    }

    public function addInternalNote(Model $user, Ticket $ticket): bool
    {
        return $this->update($user, $ticket);
    }

    public function delete(Model $user, Ticket $ticket): bool
    {
        if ($this->blocked($user, $ticket)) {
            return false;
        }

        return TicketVisibility::manages($user);
    }

    public function approve(Model $user, Ticket $ticket): bool
    {
        if ($this->blocked($user, $ticket)) {
            return false;
        }

        if (TicketVisibility::manages($user)) {
            return true;
        }

        return $ticket->approvals()
            ->where('approver_id', $user->getKey())
            ->where('status', ApprovalStatus::PENDING->value)
            ->exists();
    }

    private function blocked(Model $user, Ticket $ticket): bool
    {
        return (bool) $ticket->is_confidential && ! TicketVisibility::engaged($user, $ticket);
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

    private function inDepartment(Model $user, Ticket $ticket): bool
    {
        if (! $ticket->department_id) {
            return false;
        }

        return in_array((int) $ticket->department_id, Department::agentDepartmentIds($user), true);
    }
}
