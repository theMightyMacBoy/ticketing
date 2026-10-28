<?php

namespace Coderflex\LaravelTicket\Enums;

enum Impact: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';
}
