<?php

namespace Coderflex\LaravelTicket\Mail;

use Coderflex\LaravelTicket\Enums\EmailDirection;
use Coderflex\LaravelTicket\Models\AssignmentGroup;
use Coderflex\LaravelTicket\Models\Attachment;
use Coderflex\LaravelTicket\Models\EmailMessage;
use Coderflex\LaravelTicket\Models\Mailbox;
use Coderflex\LaravelTicket\Models\Message;
use Coderflex\LaravelTicket\Models\Ticket;
use Coderflex\LaravelTicket\RichText\HtmlBody;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TicketMailer
{
    public function deliver(Ticket $ticket, Message $message): void
    {
        if ((int) $message->user_id === (int) $ticket->user_id) {
            return;
        }

        $mailbox = $this->mailboxFor($ticket);

        if (! $mailbox instanceof Mailbox) {
            return;
        }

        $to = $this->recipient($ticket);

        if ($to === null || strcasecmp($to, $mailbox->address) === 0) {
            return;
        }

        $inReplyTo = EmailMessage::query()
            ->where('ticket_id', $ticket->getKey())
            ->whereNotNull('internet_message_id')
            ->latest('id')
            ->value('internet_message_id');

        $references = EmailMessage::query()
            ->where('ticket_id', $ticket->getKey())
            ->whereNotNull('internet_message_id')
            ->latest('id')
            ->limit(20)
            ->pluck('internet_message_id')
            ->reverse()
            ->map(fn ($id) => '<'.$id.'>')
            ->implode(' ');

        $messageId = (string) InboundEmail::normalizeId(sprintf(
            't%d.%s@%s',
            $ticket->getKey(),
            (string) Str::uuid(),
            $mailbox->domain()
        ));

        $subject = $this->subject($ticket);
        $bodyText = (string) $message->message;
        $bodyHtml = null;
        $inline = [];

        if ($message->format === 'html') {
            $embedded = app(HtmlBody::class)->embed($bodyText, $ticket);
            $bodyHtml = $embedded['html'];
            $bodyText = $embedded['text'];
            $inline = $embedded['inline'];
        }

        $mailable = new TicketReplyMail(
            fromAddress: $mailbox->address,
            fromName: $mailbox->name,
            bodyText: $bodyText,
            subjectLine: $subject,
            messageId: $messageId,
            inReplyTo: is_string($inReplyTo) ? $inReplyTo : null,
            references: $references,
            ticketNumber: (string) $ticket->ticket_number,
            bodyHtml: $bodyHtml,
            inline: $inline,
            files: $this->files($message),
        );

        $mailer = config('laravel_ticket.mail.mailer');

        Mail::mailer(is_string($mailer) && $mailer !== '' ? $mailer : null)
            ->to($to)
            ->send($mailable);

        EmailMessage::query()->create([
            'mailbox_id' => $mailbox->getKey(),
            'ticket_id' => $ticket->getKey(),
            'message_id' => $message->getKey(),
            'direction' => EmailDirection::OUTBOUND->value,
            'internet_message_id' => $messageId,
            'in_reply_to' => is_string($inReplyTo) ? $inReplyTo : null,
            'from_address' => $mailbox->address,
            'subject' => Str::limit($subject, 255, ''),
            'received_at' => now(),
        ]);
    }

    /**
     * @return list<array{bytes: string, mime: string, name: string}>
     */
    private function files(Message $message): array
    {
        $files = [];

        foreach ($message->attachments()->get() as $file) {
            if (! $file instanceof Attachment || $file->is_inline || $file->path === '') {
                continue;
            }

            $bytes = Storage::disk($file->disk)->get($file->path);

            if (! is_string($bytes) || $bytes === '') {
                continue;
            }

            $files[] = [
                'bytes' => $bytes,
                'mime' => is_string($file->mime) && $file->mime !== '' ? $file->mime : 'application/octet-stream',
                'name' => $file->original_name !== '' ? $file->original_name : 'file',
            ];
        }

        return $files;
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

    private function recipient(Ticket $ticket): ?string
    {
        if (is_string($ticket->requester_email) && $ticket->requester_email !== '') {
            return $ticket->requester_email;
        }

        $related = $ticket->user()->first();

        if ($related === null) {
            return null;
        }

        $email = $related->getAttribute('email');

        return is_string($email) && $email !== '' ? $email : null;
    }

    private function subject(Ticket $ticket): string
    {
        $number = (string) $ticket->ticket_number;
        $title = (string) $ticket->title;

        if ($number !== '' && str_contains(strtoupper($title), strtoupper($number))) {
            return $title;
        }

        return $number !== '' ? "[{$number}] {$title}" : $title;
    }
}
