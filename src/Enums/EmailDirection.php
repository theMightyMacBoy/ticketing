<?php

namespace Coderflex\LaravelTicket\Enums;

enum EmailDirection: string
{
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';
}
