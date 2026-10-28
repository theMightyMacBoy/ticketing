<?php

namespace Coderflex\LaravelTicket\Enums;

enum Channel: string
{
    case PORTAL = 'portal';
    case EMAIL = 'email';
    case PHONE = 'phone';
    case CHAT = 'chat';
    case MONITORING = 'monitoring';
}
