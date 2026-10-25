<?php

namespace Coderflex\LaravelTicket\Commands;

use Coderflex\LaravelTicket\Mail\MailboxIntake;
use Coderflex\LaravelTicket\Models\Mailbox;
use Illuminate\Console\Command;
use Throwable;

class FetchMailCommand extends Command
{
    protected $signature = 'tickets:fetch-mail {--mailbox= : Mailbox id or address. Omit to fetch every active mailbox.}';

    protected $description = 'Fetch unread Exchange Online mail and open or update tickets';

    public function handle(MailboxIntake $intake): int
    {
        $query = Mailbox::query()->where('is_active', true);
        $filter = $this->option('mailbox');

        if (is_string($filter) && $filter !== '') {
            $query->where(function ($builder) use ($filter) {
                $builder->where('address', $filter);

                if (ctype_digit($filter)) {
                    $builder->orWhere('id', $filter);
                }
            });
        }

        $failed = 0;

        $query->orderBy('id')->each(function (Mailbox $mailbox) use ($intake, &$failed) {
            try {
                $count = $intake->ingest($mailbox);
                $this->info("{$mailbox->address}: {$count} messages.");
            } catch (Throwable $exception) {
                $failed++;
                $this->error("{$mailbox->address}: {$exception->getMessage()}");
            }
        });

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
