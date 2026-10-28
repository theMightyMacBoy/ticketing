<?php

namespace Coderflex\LaravelTicket\Enums;

enum Status: string
{
    case NEW = 'new';
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case PENDING = 'pending';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';
    case ARCHIVED = 'archived';

    /**
     * Statuses that still need an agent.
     *
     * @return array<int, string>
     */
    public static function active(): array
    {
        return [
            self::NEW->value,
            self::OPEN->value,
            self::IN_PROGRESS->value,
            self::PENDING->value,
        ];
    }

    /**
     * Statuses that have left the working queue.
     *
     * @return array<int, string>
     */
    public static function terminal(): array
    {
        return [
            self::RESOLVED->value,
            self::CLOSED->value,
            self::CANCELLED->value,
            self::ARCHIVED->value,
        ];
    }
}
