<?php

namespace Coderflex\LaravelTicket\Numbering;

use Coderflex\LaravelTicket\Models\TicketSequence;
use Coderflex\LaravelTicket\Models\TicketTypeDefinition;
use Illuminate\Support\Facades\DB;

class TicketNumberGenerator
{
    public function next(string $type, ?int $departmentId = null): string
    {
        $prefix = TicketTypeDefinition::resolvePrefix($type, $departmentId)
            ?? (string) config('laravel_ticket.numbering.prefixes.'.$type, 'INC');
        $pad = (int) config('laravel_ticket.numbering.pad', 6);

        $current = DB::transaction(function () use ($prefix) {
            $existing = TicketSequence::query()->where('prefix', $prefix)->lockForUpdate()->first();

            if ($existing) {
                $existing->current++;
                $existing->save();

                return (int) $existing->current;
            }

            $created = TicketSequence::query()->create([
                'prefix' => $prefix,
                'current' => 1,
            ]);

            return (int) $created->current;
        });

        return sprintf('%s-%0'.$pad.'d', $prefix, $current);
    }
}
