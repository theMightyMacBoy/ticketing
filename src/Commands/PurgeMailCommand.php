<?php

namespace Coderflex\LaravelTicket\Commands;

use Coderflex\LaravelTicket\Mail\MailboxPurger;
use Coderflex\LaravelTicket\Models\Mailbox;
use Illuminate\Console\Command;
use Throwable;

class PurgeMailCommand extends Command
{
    protected $signature = 'tickets:purge-mail {--mailbox= : Mailbox id or address. Omit to purge every active mailbox with a retention rule.}';

    protected $description = 'Delete read Exchange inbox mail older than each mailbox retention rule';

    public function handle(MailboxPurger $purger): int
    {
        $query = Mailbox::query()->where('is_active', true);
        $filter = $this->option('mailbox');
        $targeted = is_string($filter) && $filter !== '';

        if ($targeted) {
            $query->where(function ($builder) use ($filter) {
                $builder->where('address', $filter);

                if (ctype_digit($filter)) {
                    $builder->orWhere('id', $filter);
                }
            });
        } else {
            $query->where('delete_read_after_days', '>=', 1);
        }

        $failed = 0;
        $limit = max(1, (int) config('laravel_ticket.mail.purge_limit', 2000));

        $query->orderBy('id')->each(function (Mailbox $mailbox) use ($purger, &$failed, $limit) {
            $days = $mailbox->delete_read_after_days;

            if (! is_int($days) || $days < 1) {
                $this->line("{$mailbox->address}: retention is off.");

                return;
            }

            try {
                $count = $purger->purge($mailbox);
                $more = $count >= $limit ? ' More read mail may remain.' : '';
                $this->info("{$mailbox->address}: deleted {$count} read messages older than {$days} days.{$more}");
            } catch (Throwable $exception) {
                $failed++;
                $this->error("{$mailbox->address}: {$exception->getMessage()}");
            }
        });

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
