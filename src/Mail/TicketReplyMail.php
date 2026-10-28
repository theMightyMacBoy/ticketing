<?php

namespace Coderflex\LaravelTicket\Mail;

use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class TicketReplyMail extends Mailable
{
    /**
     * @param  list<array{cid: string, bytes: string, mime: string, name: string}>  $inline
     * @param  list<array{bytes: string, mime: string, name: string}>  $files
     */
    public function __construct(
        public string $fromAddress,
        public string $fromName,
        public string $bodyText,
        public string $subjectLine,
        public string $messageId,
        public ?string $inReplyTo,
        public string $references,
        public string $ticketNumber,
        public ?string $bodyHtml = null,
        public array $inline = [],
        public array $files = [],
    ) {}

    public function build(): self
    {
        $mail = $this
            ->from($this->fromAddress, $this->fromName)
            ->subject($this->subjectLine)
            ->text('laravel-ticket::emails.reply-text', [
                'body' => $this->bodyText,
                'ticketNumber' => $this->ticketNumber,
            ]);

        if (is_string($this->bodyHtml) && $this->bodyHtml !== '') {
            $mail->view('laravel-ticket::emails.reply', [
                'body' => $this->bodyHtml,
                'ticketNumber' => $this->ticketNumber,
            ]);
        }

        return $mail->withSymfonyMessage(function (Email $email) {
            $headers = $email->getHeaders();
            $headers->addIdHeader('Message-ID', $this->messageId);

            if (is_string($this->inReplyTo) && $this->inReplyTo !== '') {
                $headers->addIdHeader('In-Reply-To', $this->inReplyTo);
            }

            if ($this->references !== '') {
                $headers->addTextHeader('References', $this->references);
            }

            foreach ($this->inline as $part) {
                if (! str_contains($part['cid'], '@')) {
                    continue;
                }

                $email->addPart(
                    (new DataPart($part['bytes'], $part['name'], $part['mime']))->asInline()->setContentId($part['cid'])
                );
            }

            foreach ($this->files as $part) {
                $email->addPart(new DataPart($part['bytes'], $part['name'], $part['mime']));
            }
        });
    }
}
