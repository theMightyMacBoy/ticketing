<?php

namespace Coderflex\LaravelTicket\Events;

use Coderflex\LaravelTicket\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketOpened
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Ticket $ticket) {}
}
