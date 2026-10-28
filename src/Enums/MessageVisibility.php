<?php

namespace Coderflex\LaravelTicket\Enums;

enum MessageVisibility: string
{
    case PUBLIC = 'public';
    case INTERNAL = 'internal';
}
