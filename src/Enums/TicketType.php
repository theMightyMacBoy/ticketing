<?php

namespace Coderflex\LaravelTicket\Enums;

enum TicketType: string
{
    case INCIDENT = 'incident';
    case SERVICE_REQUEST = 'service_request';
    case PROBLEM = 'problem';
    case CHANGE = 'change';
}
