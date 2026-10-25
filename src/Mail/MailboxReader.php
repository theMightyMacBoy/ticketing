<?php

namespace Coderflex\LaravelTicket\Mail;

use Carbon\CarbonInterface;
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

    /**
     * Delete read inbox messages received before this instant.
     */
    public function deleteReadBefore(Mailbox $mailbox, CarbonInterface $before): int;
}
