<?php

namespace Coderflex\LaravelTicket\Desk;

use Coderflex\LaravelTicket\Catalog\CatalogForm;
use Coderflex\LaravelTicket\Enums\Channel;
use Coderflex\LaravelTicket\Enums\Impact;
use Coderflex\LaravelTicket\Enums\TicketType;
use Coderflex\LaravelTicket\Enums\Urgency;
use Coderflex\LaravelTicket\Models\AssignmentGroup;
use Coderflex\LaravelTicket\Models\Service;
use Coderflex\LaravelTicket\Models\Ticket;
use Coderflex\LaravelTicket\Sla\PriorityMatrix;
use Illuminate\Database\Eloquent\Model;

class TicketDesk
{
    public function __construct(private PriorityMatrix $matrix) {}

    /**
     * Open a ticket and score its priority from impact and urgency.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function open(array $attributes, ?Model $requester = null): Ticket
    {
        $requester ??= auth()->user();
        $impact = (string) ($attributes['impact'] ?? Impact::LOW->value);
        $urgency = (string) ($attributes['urgency'] ?? Urgency::LOW->value);
        $attributes = $this->inheritRouting($attributes);
        $serviceId = isset($attributes['service_id']) ? (int) $attributes['service_id'] : null;
        CatalogForm::assertSatisfied($serviceId ?: null, $attributes);

        $ticket = Ticket::query()->create([
            'user_id' => $attributes['user_id'] ?? $requester?->getKey(),
            'affected_user_id' => $attributes['affected_user_id'] ?? null,
            'organization_id' => $attributes['organization_id'] ?? null,
            'assignment_group_id' => $attributes['assignment_group_id'] ?? null,
            'service_id' => $attributes['service_id'] ?? null,
            'title' => $attributes['title'],
            'message' => $attributes['message'] ?? null,
            'type' => $attributes['type'] ?? TicketType::INCIDENT->value,
            'impact' => $impact,
            'urgency' => $urgency,
            'priority' => $attributes['priority'] ?? $this->matrix->calculate($impact, $urgency),
            'channel' => $attributes['channel'] ?? Channel::PORTAL->value,
            'is_major' => (bool) ($attributes['is_major'] ?? false),
            'mailbox_id' => $attributes['mailbox_id'] ?? null,
            'requester_email' => $attributes['requester_email'] ?? null,
            'department_id' => $attributes['department_id'] ?? null,
            'custom_attributes' => $attributes['custom_attributes'] ?? null,
        ]);

        if (! empty($attributes['categories'])) {
            $ticket->attachCategories($attributes['categories']);
        }

        if (! empty($attributes['labels'])) {
            $ticket->attachLabels($attributes['labels']);
        }

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function inheritRouting(array $attributes): array
    {
        $service = null;

        if (! empty($attributes['service_id'])) {
            $found = Service::query()->find($attributes['service_id']);
            $service = $found instanceof Service ? $found : null;
        }

        if ($service instanceof Service) {
            $attributes['assignment_group_id'] ??= $service->assignment_group_id;
            $attributes['department_id'] ??= $service->department_id;
        }

        if (empty($attributes['department_id']) && ! empty($attributes['assignment_group_id'])) {
            $group = AssignmentGroup::query()->find($attributes['assignment_group_id']);

            if ($group instanceof AssignmentGroup && $group->department_id) {
                $attributes['department_id'] = $group->department_id;
            }
        }

        return $attributes;
    }
}
