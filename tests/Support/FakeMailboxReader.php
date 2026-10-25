<?php

namespace Coderflex\LaravelTicket\Tests\Support;

use Carbon\CarbonInterface;
use Coderflex\LaravelTicket\Mail\InboundEmail;
use Coderflex\LaravelTicket\Mail\MailboxReader;
use Coderflex\LaravelTicket\Models\Mailbox;

class FakeMailboxReader implements MailboxReader
{
    /** @var array<int, InboundEmail> */
    public array $messages = [];

    /** @var array<int, string> */
    public array $marked = [];

    public ?CarbonInterface $deleteBefore = null;

    public int $deleteCount = 0;

    public function fetch(Mailbox $mailbox): array
    {
        return $this->messages;
    }

    public function markProcessed(Mailbox $mailbox, string $graphId): void
    {
        $this->marked[] = $graphId;
    }

    public function deleteReadBefore(Mailbox $mailbox, CarbonInterface $before): int
    {
        $this->deleteBefore = $before;

        return $this->deleteCount;
    }
}
