<?php

namespace Coderflex\LaravelTicket\Mail;

use Illuminate\Mail\Mailable;

class TicketNoticeMail extends Mailable
{
    public function __construct(
        public string $fromAddress,
        public string $subjectLine,
        public string $bodyText,
    ) {}

    public function build(): self
    {
        return $this
            ->from($this->fromAddress)
            ->subject($this->subjectLine)
            ->text('laravel-ticket::emails.notice-text', [
                'body' => $this->bodyText,
            ]);
    }
}
