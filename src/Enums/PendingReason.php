<?php

namespace Coderflex\LaravelTicket\Enums;

enum PendingReason: string
{
    case CUSTOMER = 'customer';
    case VENDOR = 'vendor';
    case CHANGE = 'change';
    case OTHER = 'other';
}
