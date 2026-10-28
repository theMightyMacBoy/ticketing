<?php

namespace Coderflex\LaravelTicket\Enums;

enum Priority: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';
}
