<?php

namespace Coderflex\LaravelTicket\Mail;

use Coderflex\LaravelTicket\Models\Mailbox;

interface MailboxReader
{
    /**
     * Unread inbox messages for this Exchange Online mailbox.
     *
     * @return array<int, InboundEmail>
     */
    public function fetch(Mailbox $mailbox): array;

    public function markProcessed(Mailbox $mailbox, string $graphId): void;
}
