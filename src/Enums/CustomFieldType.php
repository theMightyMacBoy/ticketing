<?php

namespace Coderflex\LaravelTicket\Enums;

enum CustomFieldType: string
{
    case STRING = 'string';
    case NUMBER = 'number';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case SELECT = 'select';
}
