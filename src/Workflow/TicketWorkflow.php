<?php

namespace Coderflex\LaravelTicket\Workflow;

use Coderflex\LaravelTicket\Enums\ApprovalStatus;
use Coderflex\LaravelTicket\Enums\Status;
use Coderflex\LaravelTicket\Enums\TicketType;
use Coderflex\LaravelTicket\Exceptions\ApprovalRequired;
use Coderflex\LaravelTicket\Exceptions\InvalidTicketTransition;
use Coderflex\LaravelTicket\Exceptions\MissingCustomFields;
use Coderflex\LaravelTicket\Models\Ticket;

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

        if (
            $to === Status::IN_PROGRESS->value
            && $ticket->type === TicketType::CHANGE->value
            && config('laravel_ticket.workflow.require_change_approval', true)
            && ! $ticket->approvals()->where('status', ApprovalStatus::APPROVED->value)->exists()
        ) {
            throw new ApprovalRequired('A change ticket needs an approval before work starts.');
        }
    }
}
