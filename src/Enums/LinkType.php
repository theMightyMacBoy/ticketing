<?php

namespace Coderflex\LaravelTicket\Enums;

enum LinkType: string
{
    case RELATED = 'related';
    case PARENT = 'parent';
    case DUPLICATE = 'duplicate';
    case CAUSED_BY = 'caused_by';
}
