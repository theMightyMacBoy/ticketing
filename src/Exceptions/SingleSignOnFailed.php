<?php

namespace Coderflex\LaravelTicket\Exceptions;

use RuntimeException;

class SingleSignOnFailed extends RuntimeException
{
    public function __construct(string $message, public bool $retryable = false)
    {
        parent::__construct($message);
    }
}
