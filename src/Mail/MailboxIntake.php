<?php

namespace Coderflex\LaravelTicket\Mail;

use Coderflex\LaravelTicket\Desk\TicketDesk;
use Coderflex\LaravelTicket\Enums\Channel;
use Coderflex\LaravelTicket\Enums\EmailDirection;
use Coderflex\LaravelTicket\Enums\MessageVisibility;
use Coderflex\LaravelTicket\Enums\TicketType;
use Coderflex\LaravelTicket\Exceptions\MailboxMisconfigured;
use Coderflex\LaravelTicket\Exceptions\UnmatchedSender;
use Coderflex\LaravelTicket\Models\EmailMessage;
use Coderflex\LaravelTicket\Models\Mailbox;
use Coderflex\LaravelTicket\Models\Message;
use Coderflex\LaravelTicket\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MailboxIntake
{
    public function __construct(
        private MailboxReader $reader,
        private TicketDesk $desk,
    ) {}

    public function ingest(Mailbox $mailbox): int
    {
        $processed = 0;

        foreach ($this->reader->fetch($mailbox) as $email) {
            if ($this->alreadySeen($mailbox, $email->graphId)) {
                $this->reader->markProcessed($mailbox, $email->graphId);

                continue;
            }

            if ($this->shouldSkip($mailbox, $email)) {
                $this->reader->markProcessed($mailbox, $email->graphId);

                continue;
            }

            DB::transaction(function () use ($mailbox, $email) {
                $ticket = $this->findTicket($mailbox, $email);

                if ($ticket instanceof Ticket) {
                    $this->appendReply($mailbox, $ticket, $email);

                    return;
                }

                $this->openTicket($mailbox, $email);
            });

            $this->reader->markProcessed($mailbox, $email->graphId);
            $processed++;
        }

        $mailbox->forceFill(['last_synced_at' => now()])->save();

        return $processed;
    }

    private function alreadySeen(Mailbox $mailbox, string $graphId): bool
    {
        return EmailMessage::query()
            ->where('mailbox_id', $mailbox->getKey())
            ->where('graph_id', $graphId)
            ->exists();
    }

    private function shouldSkip(Mailbox $mailbox, InboundEmail $email): bool
    {
        if ($email->fromAddress === '') {
            return true;
        }

        return strcasecmp($email->fromAddress, $mailbox->address) === 0;
    }

    private function findTicket(Mailbox $mailbox, InboundEmail $email): ?Ticket
    {
        $ids = $email->referenceIds();

        if ($ids !== []) {
            $ticketId = EmailMessage::query()
                ->where('mailbox_id', $mailbox->getKey())
                ->whereIn('internet_message_id', $ids)
                ->latest('id')
                ->value('ticket_id');

            $ticket = $ticketId ? Ticket::query()->find($ticketId) : null;

            if ($ticket instanceof Ticket) {
                return $ticket;
            }
        }

        $number = $this->ticketNumberFromSubject($email->subject);

        if ($number === null) {
            return null;
        }

        $ticket = Ticket::query()->where('ticket_number', $number)->first();

        return $ticket instanceof Ticket ? $ticket : null;
    }

    private function appendReply(Mailbox $mailbox, Ticket $ticket, InboundEmail $email): void
    {
        $message = $ticket->messages()->make([
            'user_id' => $this->senderId($email),
            'message' => $email->body !== '' ? $email->body : '(no body)',
            'visibility' => MessageVisibility::PUBLIC->value,
            'sender_email' => $email->fromAddress,
            'sender_name' => $email->fromName,
        ]);

        if (! $message instanceof Message) {
            throw new MailboxMisconfigured('The configured message model cannot record an email reply.');
        }

        $message->suppressOutboundMail = true;
        $message->save();

        $this->record($mailbox, $ticket, $email, $message);
    }

    private function openTicket(Mailbox $mailbox, InboundEmail $email): void
    {
        $primary = $mailbox->primaryGroup();

        if ($primary === null) {
            throw new MailboxMisconfigured("Mailbox [{$mailbox->address}] has no primary support group.");
        }

        $ticket = $this->desk->open([
            'user_id' => $this->senderId($email),
            'assignment_group_id' => $primary->getKey(),
            'mailbox_id' => $mailbox->getKey(),
            'requester_email' => $email->fromAddress,
            'title' => $this->subjectTitle($email->subject),
            'message' => $email->body !== '' ? $email->body : '(no body)',
            'channel' => Channel::EMAIL->value,
            'type' => TicketType::INCIDENT->value,
        ]);

        $this->record($mailbox, $ticket, $email, null);
    }

    private function record(Mailbox $mailbox, Ticket $ticket, InboundEmail $email, ?Message $message): void
    {
        $references = $email->referenceIds();

        EmailMessage::query()->create([
            'mailbox_id' => $mailbox->getKey(),
            'ticket_id' => $ticket->getKey(),
            'message_id' => $message?->getKey(),
            'direction' => EmailDirection::INBOUND->value,
            'graph_id' => $email->graphId,
            'internet_message_id' => $this->limit(InboundEmail::normalizeId($email->internetMessageId)),
            'in_reply_to' => $this->limit(InboundEmail::normalizeId($email->inReplyTo) ?? ($references[0] ?? null)),
            'from_address' => $email->fromAddress,
            'subject' => $this->limit($email->subject, 255) ?? '',
            'received_at' => InboundEmail::receivedAt($email->receivedAt->toIso8601String()),
        ]);
    }

    private function senderId(InboundEmail $email): int
    {
        $callback = config('laravel_ticket.mail.resolve_sender');

        if (is_callable($callback)) {
            $resolved = $callback($email);

            if ($resolved instanceof Model) {
                return (int) $resolved->getKey();
            }

            if (is_int($resolved) || (is_string($resolved) && ctype_digit($resolved))) {
                return (int) $resolved;
            }
        }

        $model = config('auth.providers.users.model');

        if (is_string($model) && class_exists($model)) {
            $user = $model::query()->where('email', $email->fromAddress)->first();

            if ($user instanceof Model) {
                return (int) $user->getKey();
            }
        }

        $system = config('laravel_ticket.mail.system_user_id');

        if (is_int($system) || (is_string($system) && ctype_digit($system))) {
            return (int) $system;
        }

        throw new UnmatchedSender(
            "No user matches [{$email->fromAddress}]. Set laravel_ticket.mail.system_user_id or resolve_sender."
        );
    }

    private function subjectTitle(string $subject): string
    {
        $title = trim($subject);
        $previous = null;

        while ($title !== $previous) {
            $previous = $title;
            $title = trim((string) preg_replace('/^(re|fw|fwd)\s*:\s*/i', '', $title));
        }

        $title = $title === '' ? '(no subject)' : $title;

        return Str::limit($title, 255, '');
    }

    private function ticketNumberFromSubject(string $subject): ?string
    {
        $prefixes = array_filter(array_values(config('laravel_ticket.numbering.prefixes', [])));
        $prefixes = array_map(fn ($prefix) => preg_quote((string) $prefix, '/'), $prefixes);

        if ($prefixes === []) {
            return null;
        }

        $pattern = '/\b((?:'.implode('|', $prefixes).')-\d+)\b/i';

        if (! preg_match($pattern, $subject, $matches)) {
            return null;
        }

        return strtoupper($matches[1]);
    }

    private function limit(?string $value, int $length = 512): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Str::limit($value, $length, '');
    }
}
