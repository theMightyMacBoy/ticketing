<?php

namespace Coderflex\LaravelTicket\Reporting;

use Coderflex\LaravelTicket\Enums\Status;
use Coderflex\LaravelTicket\Models\Ticket;

class ServiceDeskReport
{
    public function backlog(): int
    {
        return Ticket::query()->whereIn('status', Status::active())->count();
    }

    public function breached(): int
    {
        return Ticket::query()
            ->where(function ($query) {
                $query->where('response_breached', true)
                    ->orWhere('resolution_breached', true);
            })
            ->whereIn('status', Status::active())
            ->count();
    }

    /**
     * Share of resolved or closed tickets that met the resolution target.
     * Null when nothing has finished.
     */
    public function slaAttainment(): ?float
    {
        $finished = Ticket::query()->whereIn('status', [
            Status::RESOLVED->value,
            Status::CLOSED->value,
        ]);

        $total = (clone $finished)->count();

        if ($total === 0) {
            return null;
        }

        $met = (clone $finished)->where('resolution_breached', false)->count();

        return round(($met / $total) * 100, 1);
    }

    public function meanTimeToResolveMinutes(): ?float
    {
        $samples = Ticket::query()
            ->whereNotNull('resolved_at')
            ->get(['created_at', 'resolved_at']);

        if ($samples->isEmpty()) {
            return null;
        }

        $average = $samples->avg(function (Ticket $ticket) {
            return ($ticket->resolved_at->getTimestamp() - $ticket->created_at->getTimestamp()) / 60;
        });

        return round((float) $average, 1);
    }

    /**
     * @return array<string, int>
     */
    public function volumeByType(): array
    {
        return $this->volume('type');
    }

    /**
     * @return array<string, int>
     */
    public function volumeByPriority(): array
    {
        return $this->volume('priority');
    }

    /**
     * @return array<string, int>
     */
    private function volume(string $column): array
    {
        return Ticket::query()
            ->selectRaw($column.', COUNT(*) as aggregate')
            ->groupBy($column)
            ->pluck('aggregate', $column)
            ->map(fn ($count) => (int) $count)
            ->all();
    }
}
