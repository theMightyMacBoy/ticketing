<?php

namespace Coderflex\LaravelTicket\Http;

use Coderflex\LaravelTicket\Access\TicketVisibility;
use Coderflex\LaravelTicket\Models\Department;
use Illuminate\Database\Eloquent\Model;

class DeskAccess
{
    public static function manages(Model $user): bool
    {
        return TicketVisibility::manages($user);
    }

    public static function canConfigure(Model $user): bool
    {
        return self::manages($user) || Department::agentDepartmentIds($user) !== [];
    }
}
