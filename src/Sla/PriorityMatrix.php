<?php

namespace Coderflex\LaravelTicket\Sla;

class PriorityMatrix
{
    /**
     * Impact × urgency. Normal is treated as medium so older tickets still score.
     */
    public function calculate(string $impact, string $urgency): string
    {
        $weight = [
            'low' => 1,
            'normal' => 2,
            'medium' => 2,
            'high' => 3,
            'critical' => 4,
        ];

        $score = ($weight[$impact] ?? 1) * ($weight[$urgency] ?? 1);

        return match (true) {
            $score >= 12 => 'critical',
            $score >= 6 => 'high',
            $score >= 3 => 'medium',
            default => 'low',
        };
    }
}
