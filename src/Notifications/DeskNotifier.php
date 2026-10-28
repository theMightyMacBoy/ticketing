<?php

namespace Coderflex\LaravelTicket\Notifications;

use Coderflex\LaravelTicket\Enums\EmailDirection;
use Coderflex\LaravelTicket\Events\ApprovalRequested;
use Coderflex\LaravelTicket\Events\TicketAssigned;
use Coderflex\LaravelTicket\Events\TicketOpened;
use Coderflex\LaravelTicket\Events\TicketSlaBreached;
use Coderflex\LaravelTicket\Mail\InboundEmail;
use Coderflex\LaravelTicket\Mail\TicketNoticeMail;
use Coderflex\LaravelTicket\Models\AssignmentGroup;
use Coderflex\LaravelTicket\Models\EmailMessage;
use Coderflex\LaravelTicket\Models\Mailbox;
use Coderflex\LaravelTicket\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class DeskNotifier
{
    public function opened(TicketOpened $event): void
    {
        $ticket = $event->ticket;
        $to = $this->requester($ticket);
        $mailbox = $this->mailboxFor($ticket);
        $from = $mailbox instanceof Mailbox ? $mailbox->address : $this->fallbackFrom();
        $number = (string) $ticket->ticket_number;
        $title = (string) $ticket->title;

        $sent = $this->send(
            $to,
            $from,
            $number !== '' ? "[{$number}] {$title}" : $title,
            "Your request {$number} has been opened: {$title}. Reply to this email and it will be added to {$number}."
        );

        if ($sent && $mailbox instanceof Mailbox) {
            $this->rememberAcknowledgement($ticket, $mailbox, $from, $number, $title);
        }
    }

    public function assigned(TicketAssigned $event): void
    {
        if ($event->assigneeId === null || $event->assigneeId === '') {
            return;
        }

        $ticket = $event->ticket;
        $assignee = $ticket->assignedToUser()->first();

        if (! $assignee instanceof Model) {
            return;
        }

        $number = (string) $ticket->ticket_number;

        $this->send(
            $this->addressOf($assignee),
            $this->fromFor($ticket),
            "{$number} was assigned to you",
            "{$number} was assigned to you: {$ticket->title}."
        );
    }

    public function approval(ApprovalRequested $event): void
    {
        $approval = $event->approval;
        $approver = $approval->approver()->first();
        $ticket = $approval->ticket()->first();

        if (! $approver instanceof Model || ! $ticket instanceof Ticket) {
            return;
        }

        $number = (string) $ticket->ticket_number;

        $this->send(
            $this->addressOf($approver),
            $this->fromFor($ticket),
            "{$number} is waiting for your approval",
            "{$number} is waiting for your approval: {$ticket->title}."
        );
    }

    public function breached(TicketSlaBreached $event): void
    {
        $ticket = $event->ticket;
        $assignee = $ticket->assignedToUser()->first();

        if (! $assignee instanceof Model) {
            return;
        }

        $number = (string) $ticket->ticket_number;

        $this->send(
            $this->addressOf($assignee),
            $this->fromFor($ticket),
            "{$number} breached its service target",
            "{$number} has breached its service target: {$ticket->title}."
        );
    }

    public function mailboxFailed(Mailbox $mailbox, Throwable $exception): void
    {
        $alert = config('laravel_ticket.mail.alert_to');

        if (! is_string($alert) || $alert === '') {
            return;
        }

        $this->send(
            $alert,
            $this->fallbackFrom(),
            "Mailbox {$mailbox->address} could not be read",
            $exception->getMessage()
        );
    }

    private function send(?string $to, string $from, string $subject, string $body): bool
    {
        if (! config('laravel_ticket.notifications.mail')) {
            return false;
        }

        if (! is_string($to) || $to === '' || strcasecmp($to, $from) === 0) {
            return false;
        }

        $mailer = config('laravel_ticket.mail.mailer');

        Mail::mailer(is_string($mailer) && $mailer !== '' ? $mailer : null)
            ->to($to)
            ->send(new TicketNoticeMail($from, $subject, $body));

        return true;
    }

    private function rememberAcknowledgement(Ticket $ticket, Mailbox $mailbox, string $from, string $number, string $title): void
    {
        $messageId = InboundEmail::normalizeId(sprintf(
            'ack%d.%s@%s',
            $ticket->getKey(),
            (string) Str::uuid(),
            $mailbox->domain()
        ));

        if ($messageId === null) {
            return;
        }

        $subject = $number !== '' ? "[{$number}] {$title}" : $title;

        EmailMessage::query()->create([
            'mailbox_id' => $mailbox->getKey(),
            'ticket_id' => $ticket->getKey(),
            'direction' => EmailDirection::OUTBOUND->value,
            'internet_message_id' => $messageId,
            'from_address' => $from,
            'subject' => Str::limit($subject, 255, ''),
            'received_at' => now(),
        ]);
    }

    private function fromFor(Ticket $ticket): string
    {
        $mailbox = $this->mailboxFor($ticket);

        return $mailbox instanceof Mailbox ? $mailbox->address : $this->fallbackFrom();
    }

    private function mailboxFor(Ticket $ticket): ?Mailbox
    {
        $mailbox = $ticket->mailbox;

        if ($mailbox instanceof Mailbox) {
            return $mailbox;
        }

        $group = $ticket->assignmentGroup;

        if (! $group instanceof AssignmentGroup) {
            return null;
        }

        $shared = $group->mailbox;

        return $shared instanceof Mailbox ? $shared : null;
    }

    private function requester(Ticket $ticket): ?string
    {
        if (is_string($ticket->requester_email) && $ticket->requester_email !== '') {
            return $ticket->requester_email;
        }

        $related = $ticket->user()->first();

        return $related instanceof Model ? $this->addressOf($related) : null;
    }

    private function addressOf(Model $user): ?string
    {
        $email = $user->getAttribute('email');

        return is_string($email) && $email !== '' ? $email : null;
    }

    private function fallbackFrom(): string
    {
        $configured = config('mail.from.address');

        return is_string($configured) && $configured !== '' ? $configured : 'desk@localhost';
    }
}
