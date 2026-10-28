<?php

namespace Coderflex\LaravelTicket\Audit;

use Coderflex\LaravelTicket\Models\Ticket;
use Coderflex\LaravelTicket\Models\TicketActivity;
use DateTimeInterface;

class ActivityLogger
{
    /**
     * Fields worth an audit row. Clock and breach columns are derived.
     *
     * @var array<int, string>
     */
    public const AUDITED = [
        'title',
        'message',
        'status',
        'priority',
        'impact',
        'urgency',
        'type',
        'assigned_to',
        'assignment_group_id',
        'is_resolved',
        'is_locked',
        'is_major',
        'channel',
        'pending_reason',
        'resolution_code_id',
        'service_id',
        'organization_id',
        'affected_user_id',
    ];

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(Ticket $ticket, string $event, array $properties = []): TicketActivity
    {
        return TicketActivity::query()->create([
            'ticket_id' => $ticket->getKey(),
            'user_id' => auth()->id(),
            'event' => $event,
            'properties' => $properties,
        ]);
    }

    /**
     * @param  array<string, mixed>  $original
     */
    public function logChanges(Ticket $ticket, array $original): ?TicketActivity
    {
        $changes = [];

        foreach (self::AUDITED as $field) {
            if (! array_key_exists($field, $original)) {
                continue;
            }

            $changes[$field] = [
                'from' => $this->value($original[$field]),
                'to' => $this->value($ticket->getAttribute($field)),
            ];
        }

        if ($changes === []) {
            return null;
        }

        $event = array_key_exists('status', $changes) ? 'status_changed' : 'updated';

        return $this->log($ticket, $event, $changes);
    }

    private function value(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || is_string($value) || $value === null) {
            return $value;
        }

        return (string) $value;
    }
}
