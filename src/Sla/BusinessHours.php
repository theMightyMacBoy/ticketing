<?php

namespace Coderflex\LaravelTicket\Sla;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

class BusinessHours
{
    public function addMinutes(CarbonInterface $start, int $minutes): Carbon
    {
        if ($minutes <= 0) {
            return Carbon::instance($start->copy());
        }

        $originTimezone = $start->timezoneName;
        $cursor = $start->copy()->timezone($this->timezone())->seconds(0);
        $remaining = $minutes;
        $guard = 0;

        while ($remaining > 0) {
            $guard++;

            if ($guard > 3700) {
                throw new RuntimeException('Business-hour calculation exceeded the safety window.');
            }

            $window = $this->window($cursor);

            if ($window === null) {
                $cursor = $cursor->copy()->addDay()->startOfDay();

                continue;
            }

            [$open, $close] = $window;

            if ($cursor->lt($open)) {
                $cursor = $open->copy();
            }

            if ($cursor->greaterThanOrEqualTo($close)) {
                $cursor = $cursor->copy()->addDay()->startOfDay();

                continue;
            }

            $available = (int) floor(($close->getTimestamp() - $cursor->getTimestamp()) / 60);

            if ($available <= 0) {
                $cursor = $cursor->copy()->addDay()->startOfDay();

                continue;
            }

            $take = min($remaining, $available);
            $cursor = $cursor->copy()->addMinutes($take);
            $remaining -= $take;
        }

        return Carbon::instance($cursor->timezone($originTimezone));
    }

    public function diffMinutes(CarbonInterface $start, CarbonInterface $end): int
    {
        if ($end->getTimestamp() <= $start->getTimestamp()) {
            return 0;
        }

        $cursor = $start->copy()->timezone($this->timezone())->seconds(0);
        $stop = $end->copy()->timezone($this->timezone());
        $total = 0;
        $guard = 0;

        while ($cursor->lt($stop)) {
            $guard++;

            if ($guard > 3700) {
                throw new RuntimeException('Business-hour calculation exceeded the safety window.');
            }

            $window = $this->window($cursor);

            if ($window === null) {
                $cursor = $cursor->copy()->addDay()->startOfDay();

                continue;
            }

            [$open, $close] = $window;

            if ($cursor->lt($open)) {
                $cursor = $open->copy();

                continue;
            }

            if ($cursor->greaterThanOrEqualTo($close)) {
                $cursor = $cursor->copy()->addDay()->startOfDay();

                continue;
            }

            $segmentEnd = $stop->lt($close) ? $stop->copy() : $close->copy();
            $total += (int) floor(($segmentEnd->getTimestamp() - $cursor->getTimestamp()) / 60);
            $cursor = $segmentEnd;

            if ($cursor->equalTo($close) && $cursor->lt($stop)) {
                $cursor = $cursor->copy()->addDay()->startOfDay();
            }
        }

        return $total;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function window(CarbonInterface $cursor): ?array
    {
        $local = $cursor->copy()->timezone($this->timezone());
        $date = $local->toDateString();

        if (in_array($date, $this->config()['holidays'] ?? [], true)) {
            return null;
        }

        $day = strtolower($local->englishDayOfWeek);
        $slot = ($this->config()['week'] ?? [])[$day] ?? null;

        if (! is_array($slot) || empty($slot['start']) || empty($slot['end'])) {
            return null;
        }

        $open = Carbon::parse($date.' '.$slot['start'], $this->timezone());
        $close = Carbon::parse($date.' '.$slot['end'], $this->timezone());

        if ($close->lessThanOrEqualTo($open)) {
            return null;
        }

        return [$open, $close];
    }

    private function timezone(): string
    {
        return (string) ($this->config()['timezone'] ?? 'UTC');
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return config('laravel_ticket.business_hours', []);
    }
}
