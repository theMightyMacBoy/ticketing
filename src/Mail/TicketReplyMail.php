<?php

namespace Coderflex\LaravelTicket\Mail;

use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

class TicketReplyMail extends Mailable
{
    public function __construct(
        public string $fromAddress,
        public string $fromName,
        public string $bodyText,
        public string $subjectLine,
        public string $messageId,
        public ?string $inReplyTo,
        public string $references,
        public string $ticketNumber,
    ) {}

    public function build(): self
    {
        return $this
            ->from($this->fromAddress, $this->fromName)
            ->subject($this->subjectLine)
            ->text('laravel-ticket::emails.reply-text', [
                'body' => $this->bodyText,
                'ticketNumber' => $this->ticketNumber,
            ])
            ->withSymfonyMessage(function (Email $email) {
                $headers = $email->getHeaders();
                $headers->addIdHeader('Message-ID', $this->messageId);

                if (is_string($this->inReplyTo) && $this->inReplyTo !== '') {
                    $headers->addIdHeader('In-Reply-To', $this->inReplyTo);
                }

                if ($this->references !== '') {
                    $headers->addTextHeader('References', $this->references);
                }
            });
    }
}
