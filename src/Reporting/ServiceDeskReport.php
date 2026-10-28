<?php

namespace Coderflex\LaravelTicket\Reporting;

use Coderflex\LaravelTicket\Enums\Status;
use Coderflex\LaravelTicket\Models\Department;
use Coderflex\LaravelTicket\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

class ServiceDeskReport
{
    private ?int $departmentId = null;

    public function forDepartment(Department|int $department): self
    {
        $report = clone $this;
        $report->departmentId = $department instanceof Department
            ? (int) $department->getKey()
            : $department;

        return $report;
    }

    public function backlog(): int
    {
        return $this->tickets()->whereIn('status', Status::active())->count();
    }

    public function breached(): int
    {
        return $this->tickets()
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
        $finished = $this->tickets()->whereIn('status', [
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
        $samples = $this->tickets()
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
        return $this->tickets()
            ->selectRaw($column.', COUNT(*) as aggregate')
            ->groupBy($column)
            ->pluck('aggregate', $column)
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @return Builder<Ticket>
     */
    private function tickets(): Builder
    {
        $query = Ticket::query();

        if ($this->departmentId !== null) {
            $query->where('department_id', $this->departmentId);
        }

        return $query;
    }
}
