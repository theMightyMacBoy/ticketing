<?php

namespace Coderflex\LaravelTicket\Contracts;

interface CanManageTickets
{
    /**
     * Agents and service-desk managers pass workflow, internal notes, and queue-wide access.
     */
    public function canManageTickets(): bool;
}
