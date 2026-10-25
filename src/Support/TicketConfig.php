<?php

namespace Coderflex\LaravelTicket\Support;

final class TicketConfig
{
    public static function table(string $name): string
    {
        $configured = config('laravel_ticket.table_names.'.$name, $name);

        if (is_array($configured)) {
            return (string) ($configured['table'] ?? $name);
        }

        return (string) $configured;
    }

    public static function model(string $name): string
    {
        return (string) config('laravel_ticket.models.'.$name);
    }
}
