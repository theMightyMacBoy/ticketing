<?php

namespace Coderflex\LaravelTicket\Desk;

use Coderflex\LaravelTicket\Enums\Channel;
use Coderflex\LaravelTicket\Enums\Impact;
use Coderflex\LaravelTicket\Enums\TicketType;
use Coderflex\LaravelTicket\Enums\Urgency;
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
}
