<?php

namespace Coderflex\LaravelTicket\Sla;

use Coderflex\LaravelTicket\Enums\Status;
use Coderflex\LaravelTicket\Models\SlaPolicy;
use Coderflex\LaravelTicket\Models\Ticket;
use Illuminate\Support\Carbon;

class SlaClock
{
    public function __construct(private BusinessHours $businessHours) {}

    public function initialize(Ticket $ticket): void
    {
        $this->applyDueDates($ticket);
        $this->syncTerminalTimestamps($ticket, '', (string) $ticket->status);
        $this->refreshBreach($ticket);
    }

    public function prepareStatusChange(Ticket $ticket, string $from, string $to): void
    {
        if ($to === Status::PENDING->value && $from !== Status::PENDING->value && $ticket->sla_paused_at === null) {
            $ticket->sla_paused_at = now();
        }

        if ($from === Status::PENDING->value && $to !== Status::PENDING->value && $ticket->sla_paused_at !== null) {
            $policy = $this->policyFor($ticket);
            $elapsed = $this->elapsedMinutes(
                $ticket->sla_paused_at,
                now(),
                (bool) ($policy?->use_business_hours)
            );
            $ticket->sla_paused_minutes = (int) $ticket->sla_paused_minutes + $elapsed;
            $ticket->sla_paused_at = null;
            $ticket->pending_reason = null;
            $this->applyDueDates($ticket);
        }

        $this->syncTerminalTimestamps($ticket, $from, $to);
        $this->refreshBreach($ticket);
    }

    public function markFirstResponse(Ticket $ticket, Carbon $at): void
    {
        if ($ticket->first_response_at !== null) {
            return;
        }

        $ticket->first_response_at = $at;
        $this->refreshBreach($ticket);
        $ticket->save();
    }

    public function refreshBreach(Ticket $ticket): void
    {
        $effectiveNow = $ticket->sla_paused_at ?? now();

        if ($ticket->response_due_at !== null) {
            $responseAt = $ticket->first_response_at ?? $effectiveNow;
            $ticket->response_breached = $responseAt->greaterThan($ticket->response_due_at);
        }

        if ($ticket->resolution_due_at !== null) {
            $resolutionAt = $ticket->resolved_at ?? $ticket->closed_at ?? $effectiveNow;
            $ticket->resolution_breached = $resolutionAt->greaterThan($ticket->resolution_due_at);
        }
    }

    public function policyFor(Ticket $ticket): ?SlaPolicy
    {
        return SlaPolicy::query()
            ->where('priority', $ticket->priority)
            ->where('ticket_type', $ticket->type)
            ->first()
            ?? SlaPolicy::query()
                ->where('priority', $ticket->priority)
                ->whereNull('ticket_type')
                ->first()
            ?? SlaPolicy::query()->where('is_default', true)->first();
    }

    public function applyDueDates(Ticket $ticket): void
    {
        $policy = $this->policyFor($ticket);

        if (! $policy) {
            return;
        }

        $ticket->sla_policy_id = $policy->getKey();
        $origin = $ticket->created_at ?? now();
        $paused = (int) $ticket->sla_paused_minutes;

        $ticket->response_due_at = $this->dueAt(
            $origin,
            $policy->response_minutes + $paused,
            (bool) $policy->use_business_hours
        );
        $ticket->resolution_due_at = $this->dueAt(
            $origin,
            $policy->resolution_minutes + $paused,
            (bool) $policy->use_business_hours
        );
    }

    private function dueAt(Carbon $origin, int $minutes, bool $businessHours): Carbon
    {
        if ($businessHours) {
            return $this->businessHours->addMinutes($origin, $minutes);
        }

        return $origin->copy()->addMinutes($minutes);
    }

    private function elapsedMinutes(Carbon $start, Carbon $end, bool $businessHours): int
    {
        if ($businessHours) {
            return $this->businessHours->diffMinutes($start, $end);
        }

        return max(0, (int) floor(($end->getTimestamp() - $start->getTimestamp()) / 60));
    }

    private function syncTerminalTimestamps(Ticket $ticket, string $from, string $to): void
    {
        if ($to === Status::RESOLVED->value) {
            $ticket->resolved_at ??= now();
            $ticket->is_resolved = true;
        }

        if ($to === Status::CLOSED->value && $ticket->is_resolved) {
            $ticket->resolved_at ??= now();
        }

        if (in_array($to, [Status::CLOSED->value, Status::CANCELLED->value, Status::ARCHIVED->value], true)) {
            $ticket->closed_at ??= now();
        }

        $reopened = in_array($to, Status::active(), true)
            && in_array($from, Status::terminal(), true);

        if ($reopened) {
            $ticket->closed_at = null;

            if (! $ticket->is_resolved) {
                $ticket->resolved_at = null;
            }
        }
    }
}
