<?php

namespace Coderflex\LaravelTicket\Commands;

use Coderflex\LaravelTicket\Enums\Status;
use Coderflex\LaravelTicket\Models\Ticket;
use Coderflex\LaravelTicket\Sla\SlaClock;
use Illuminate\Console\Command;

class EvaluateSlasCommand extends Command
{
    protected $signature = 'tickets:evaluate-slas';

    protected $description = 'Refresh SLA breach flags for tickets that are still open';

    public function handle(SlaClock $clock): int
    {
        $checked = 0;
        $breached = 0;

        Ticket::query()
            ->whereNotNull('sla_policy_id')
            ->whereIn('status', Status::active())
            ->orderBy('id')
            ->each(function (Ticket $ticket) use ($clock, &$checked, &$breached) {
                $checked++;
                $response = (bool) $ticket->response_breached;
                $resolution = (bool) $ticket->resolution_breached;

                $clock->refreshBreach($ticket);

                if (! $ticket->isDirty(['response_breached', 'resolution_breached'])) {
                    return;
                }

                $ticket->save();

                if ((! $response && $ticket->response_breached) || (! $resolution && $ticket->resolution_breached)) {
                    $breached++;
                }
            });

        $this->info("Checked {$checked} tickets. {$breached} newly breached.");

        return self::SUCCESS;
    }
}
