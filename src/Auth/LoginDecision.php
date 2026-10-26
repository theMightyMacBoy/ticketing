<?php

namespace Coderflex\LaravelTicket\Auth;

use Coderflex\LaravelTicket\Models\IdentityProvider;

class LoginDecision
{
    public function __construct(
        public string $method,
        public ?IdentityProvider $provider = null,
    ) {}
}
