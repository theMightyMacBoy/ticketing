<?php

namespace Coderflex\LaravelTicket\Enums;

enum Urgency: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';
}
