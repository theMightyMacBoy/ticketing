<?php

namespace Coderflex\LaravelTicket\Mail;

use Coderflex\LaravelTicket\Models\Mailbox;
use Illuminate\Support\Carbon;

class MailboxPurger
{
    public function __construct(private MailboxReader $reader) {}

    /**
     * Delete read inbox mail older than this mailbox's retention window.
     * The window is measured from 00:00 UTC. A null window deletes nothing.
     */
    public function purge(Mailbox $mailbox): int
    {
        $days = $mailbox->delete_read_after_days;

        if (! is_int($days) || $days < 1) {
            return 0;
        }

        $before = Carbon::now('UTC')->startOfDay()->subDays($days);

        return $this->reader->deleteReadBefore($mailbox, $before);
    }
}
