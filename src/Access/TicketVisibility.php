<?php

namespace Coderflex\LaravelTicket\Access;

use Coderflex\LaravelTicket\Contracts\CanManageTickets;
use Coderflex\LaravelTicket\Enums\ApprovalStatus;
use Coderflex\LaravelTicket\Models\Department;
use Coderflex\LaravelTicket\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TicketVisibility
{
    public static function manages(Model $user): bool
    {
        $callback = config('laravel_ticket.agents.callback');

        if (is_callable($callback) && $callback($user)) {
            return true;
        }

        return $user instanceof CanManageTickets && $user->canManageTickets();
    }

    public static function engaged(Model $user, Ticket $ticket): bool
    {
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

        if ($ticket->assignment_group_id && $ticket->assignmentGroup()
            ->whereHas('members', fn ($query) => $query->whereKey($id))
            ->exists()) {
            return true;
        }

        if ($ticket->department_id && in_array((int) $ticket->department_id, Department::agentDepartmentIds($user), true)) {
            return true;
        }

        return $ticket->approvals()
            ->where('approver_id', $id)
            ->where('status', ApprovalStatus::PENDING->value)
            ->exists();
    }

    public static function apply(Builder $query, Model $user): void
    {
        $id = $user->getKey();
        $departmentIds = Department::agentDepartmentIds($user);

        $query->where($query->qualifyColumn('user_id'), $id)
            ->orWhere($query->qualifyColumn('affected_user_id'), $id)
            ->orWhere($query->qualifyColumn('assigned_to'), $id)
            ->orWhereHas('watchers', fn (Builder $watchers) => $watchers->whereKey($id))
            ->orWhereHas('assignmentGroup.members', fn (Builder $members) => $members->whereKey($id))
            ->orWhereHas('approvals', function (Builder $approvals) use ($id) {
                $approvals->where('approver_id', $id)
                    ->where('status', ApprovalStatus::PENDING->value);
            });

        if ($departmentIds !== []) {
            $query->orWhereIn($query->qualifyColumn('department_id'), $departmentIds);
        }
    }
}
