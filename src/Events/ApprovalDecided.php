<?php

namespace Coderflex\LaravelTicket\Events;

use Coderflex\LaravelTicket\Models\Approval;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApprovalDecided
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Approval $approval) {}
}
