<?php

namespace Coderflex\LaravelTicket\Observers;

use Coderflex\LaravelTicket\Audit\ActivityLogger;
use Coderflex\LaravelTicket\Enums\MessageVisibility;
use Coderflex\LaravelTicket\Mail\TicketMailer;
use Coderflex\LaravelTicket\Models\Message;
use Coderflex\LaravelTicket\Models\Ticket;
use Coderflex\LaravelTicket\Sla\SlaClock;

class MessageObserver
{
    public function __construct(
        private SlaClock $sla,
        private ActivityLogger $activity,
        private TicketMailer $mailer,
    ) {}

    public function creating(Message $message): void
    {
        if (! $message->visibility) {
            $message->visibility = MessageVisibility::PUBLIC->value;
        }
    }

    public function created(Message $message): void
    {
        $ticket = $message->ticket;

        if (! $ticket instanceof Ticket) {
            return;
        }

        $visibility = (string) ($message->visibility ?: MessageVisibility::PUBLIC->value);

        $this->activity->log(
            $ticket,
            $visibility === MessageVisibility::INTERNAL->value ? 'note' : 'reply',
            [
                'message_id' => $message->getKey(),
                'visibility' => $visibility,
            ]
        );

        if (
            $visibility === MessageVisibility::PUBLIC->value
            && (int) $message->user_id !== (int) $ticket->user_id
        ) {
            $this->sla->markFirstResponse($ticket, $message->created_at ?? now());
        }

        if (
            $visibility === MessageVisibility::PUBLIC->value
            && ! $message->suppressOutboundMail
            && (int) $message->user_id !== (int) $ticket->user_id
        ) {
            $this->mailer->deliver($ticket, $message);
        }
    }
}
