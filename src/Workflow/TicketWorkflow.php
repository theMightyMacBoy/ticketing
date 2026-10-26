<?php

namespace Coderflex\LaravelTicket\Workflow;

use Coderflex\LaravelTicket\Enums\ApprovalStatus;
use Coderflex\LaravelTicket\Enums\Status;
use Coderflex\LaravelTicket\Enums\TicketType;
use Coderflex\LaravelTicket\Exceptions\ApprovalRequired;
use Coderflex\LaravelTicket\Exceptions\InvalidTicketTransition;
use Coderflex\LaravelTicket\Exceptions\MissingCustomFields;
use Coderflex\LaravelTicket\Models\Ticket;
use Coderflex\LaravelTicket\Models\TicketTypeDefinition;

class TicketWorkflow
{
    /**
     * Legal moves. Same-status updates are ignored by the caller.
     *
     * @var array<string, array<int, string>>
     */
    private const TRANSITIONS = [
        'new' => ['open', 'in_progress', 'pending', 'resolved', 'closed', 'cancelled', 'archived'],
        'open' => ['in_progress', 'pending', 'resolved', 'closed', 'cancelled', 'archived'],
        'in_progress' => ['open', 'pending', 'resolved', 'closed', 'cancelled', 'archived'],
        'pending' => ['open', 'in_progress', 'resolved', 'closed', 'cancelled', 'archived'],
        'resolved' => ['open', 'in_progress', 'pending', 'closed', 'archived'],
        'closed' => ['open', 'in_progress', 'resolved', 'archived'],
        'cancelled' => ['open', 'archived'],
        'archived' => ['open', 'closed'],
    ];

    public function assertTransition(Ticket $ticket, string $from, string $to): void
    {
        if ($from === '' || $from === $to) {
            return;
        }

        $allowed = self::TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new InvalidTicketTransition("Cannot move a ticket from [{$from}] to [{$to}].");
        }

        if (in_array($to, [Status::RESOLVED->value, Status::CLOSED->value], true)) {
            $missing = $ticket->missingRequiredCustomFields();

            if ($missing !== []) {
                throw new MissingCustomFields($missing);
            }
        }

        if ($to === Status::IN_PROGRESS->value && $this->mustBeApproved($ticket) && ! $this->approvalsComplete($ticket)) {
            $message = $ticket->type === TicketType::CHANGE->value
                ? 'A change ticket needs an approval before work starts.'
                : 'This ticket needs every approval before work starts.';

            throw new ApprovalRequired($message);
        }
    }

    private function mustBeApproved(Ticket $ticket): bool
    {
        if (
            $ticket->type === TicketType::CHANGE->value
            && ! config('laravel_ticket.workflow.require_change_approval', true)
        ) {
            return false;
        }

        $definition = $this->typeDefinition($ticket);

        if ($definition instanceof TicketTypeDefinition) {
            return $definition->requires_approval;
        }

        return $ticket->type === TicketType::CHANGE->value;
    }

    private function approvalsComplete(Ticket $ticket): bool
    {
        if (! $ticket->approvals()->exists()) {
            return false;
        }

        return ! $ticket->approvals()
            ->where('status', '!=', ApprovalStatus::APPROVED->value)
            ->exists();
    }

    private function typeDefinition(Ticket $ticket): ?TicketTypeDefinition
    {
        $query = TicketTypeDefinition::query()
            ->where('slug', $ticket->type)
            ->where('is_active', true);

        if ($ticket->department_id) {
            $specific = (clone $query)->where('department_id', $ticket->department_id)->first();

            if ($specific instanceof TicketTypeDefinition) {
                return $specific;
            }
        }

        $global = (clone $query)->whereNull('department_id')->first();

        return $global instanceof TicketTypeDefinition ? $global : null;
    }
}
