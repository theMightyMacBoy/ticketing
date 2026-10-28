<?php

namespace Coderflex\LaravelTicket\Concerns;

use Coderflex\LaravelTicket\Support\TicketConfig;

trait UsesConfiguredTable
{
    public function getTable()
    {
        return TicketConfig::table($this->configuredTable());
    }

    abstract protected function configuredTable(): string;
}
