<?php

namespace Coderflex\LaravelTicket\Observers;

use Coderflex\LaravelTicket\Audit\ActivityLogger;
use Coderflex\LaravelTicket\Enums\Channel;
use Coderflex\LaravelTicket\Enums\Impact;
use Coderflex\LaravelTicket\Enums\Status;
use Coderflex\LaravelTicket\Enums\TicketType;
use Coderflex\LaravelTicket\Enums\Urgency;
use Coderflex\LaravelTicket\Events\TicketAssigned;
use Coderflex\LaravelTicket\Events\TicketOpened;
use Coderflex\LaravelTicket\Events\TicketSlaBreached;
use Coderflex\LaravelTicket\Events\TicketStatusChanged;
use Coderflex\LaravelTicket\Models\AssignmentGroup;
use Coderflex\LaravelTicket\Models\Service;
use Coderflex\LaravelTicket\Models\Ticket;
use Coderflex\LaravelTicket\Numbering\TicketNumberGenerator;
use Coderflex\LaravelTicket\Sla\PriorityMatrix;
use Coderflex\LaravelTicket\Sla\SlaClock;
use Coderflex\LaravelTicket\Workflow\TicketWorkflow;
use Illuminate\Support\Str;

class TicketObserver
{
    public function __construct(
        private TicketNumberGenerator $numbers,
        private SlaClock $sla,
        private TicketWorkflow $workflow,
        private ActivityLogger $activity,
        private PriorityMatrix $matrix,
    ) {}

    public function creating(Ticket $ticket): void
    {
        $ticket->uuid ??= (string) Str::uuid();
        $ticket->type ??= TicketType::INCIDENT->value;
        $ticket->status ??= Status::NEW->value;
        $ticket->impact ??= Impact::LOW->value;
        $ticket->urgency ??= Urgency::LOW->value;
        $ticket->channel ??= Channel::PORTAL->value;
        $ticket->priority ??= $this->matrix->calculate((string) $ticket->impact, (string) $ticket->urgency);
        $ticket->created_at ??= now();
        $this->inheritDepartment($ticket);

        if (! $ticket->ticket_number) {
            $ticket->ticket_number = $this->numbers->next(
                (string) $ticket->type,
                $ticket->department_id ? (int) $ticket->department_id : null
            );
        }

        $this->sla->initialize($ticket);
    }

    public function created(Ticket $ticket): void
    {
        $this->activity->log($ticket, 'opened', [
            'ticket_number' => $ticket->ticket_number,
            'type' => $ticket->type,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
        ]);

        event(new TicketOpened($ticket));

        if ($ticket->response_breached || $ticket->resolution_breached) {
            event(new TicketSlaBreached($ticket));
        }
    }

    public function updating(Ticket $ticket): void
    {
        $ticket->auditFrom = [];

        foreach ($ticket->getDirty() as $field => $value) {
            $ticket->auditFrom[$field] = $ticket->getOriginal($field);
        }

        if ($ticket->isDirty('status')) {
            $from = (string) $ticket->getOriginal('status');
            $to = (string) $ticket->status;

            $this->workflow->assertTransition($ticket, $from, $to);
            $this->sla->prepareStatusChange($ticket, $from, $to);
        }

        if ($ticket->isDirty('priority') || $ticket->isDirty('type')) {
            $this->sla->applyDueDates($ticket);
            $this->sla->refreshBreach($ticket);
        }
    }

    public function updated(Ticket $ticket): void
    {
        $original = $ticket->auditFrom;

        $this->activity->logChanges($ticket, $original);

        if (array_key_exists('status', $original)) {
            event(new TicketStatusChanged(
                $ticket,
                (string) $original['status'],
                (string) $ticket->status,
            ));
        }

        if (array_key_exists('assigned_to', $original)) {
            event(new TicketAssigned($ticket, $ticket->assigned_to));
        }

        $wasResponse = array_key_exists('response_breached', $original)
            ? (bool) $original['response_breached']
            : (bool) $ticket->response_breached;
        $wasResolution = array_key_exists('resolution_breached', $original)
            ? (bool) $original['resolution_breached']
            : (bool) $ticket->resolution_breached;

        if ((! $wasResponse && $ticket->response_breached) || (! $wasResolution && $ticket->resolution_breached)) {
            event(new TicketSlaBreached($ticket));
        }

        $ticket->auditFrom = [];
    }

    private function inheritDepartment(Ticket $ticket): void
    {
        if ($ticket->department_id) {
            return;
        }

        if ($ticket->assignment_group_id) {
            $group = AssignmentGroup::query()->find($ticket->assignment_group_id);

            if ($group instanceof AssignmentGroup && $group->department_id) {
                $ticket->department_id = $group->department_id;

                return;
            }
        }

        if (! $ticket->service_id) {
            return;
        }

        $service = Service::query()->find($ticket->service_id);

        if ($service instanceof Service && $service->department_id) {
            $ticket->department_id = $service->department_id;
        }
    }
}
