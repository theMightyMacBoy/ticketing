<?php

namespace Coderflex\LaravelTicket\Exceptions;

use RuntimeException;

class MissingCustomFields extends RuntimeException
{
    /**
     * @param  array<int, string>  $fields
     */
    public function __construct(public array $fields)
    {
        parent::__construct('Missing required custom fields: '.implode(', ', $fields));
    }
}
