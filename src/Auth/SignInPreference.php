<?php

namespace Coderflex\LaravelTicket\Auth;

use Coderflex\LaravelTicket\Models\AuthIdentity;
use Coderflex\LaravelTicket\Models\IdentityProvider;
use InvalidArgumentException;

class SignInPreference
{
    public function apply(string $email, string $method): void
    {
        $email = strtolower(trim($email));

        if ($method === 'inherit') {
            AuthIdentity::query()->where('email', $email)->delete();

            return;
        }

        if ($method === 'local') {
            AuthIdentity::query()->updateOrCreate(['email' => $email], [
                'method' => 'local',
                'identity_provider_id' => null,
                'subject' => null,
            ]);

            return;
        }

        $provider = IdentityProvider::forEmail($email);

        if (! $provider instanceof IdentityProvider) {
            throw new InvalidArgumentException('Single sign-on is not configured for that email domain.');
        }

        AuthIdentity::query()->updateOrCreate(['email' => $email], [
            'method' => 'sso',
            'identity_provider_id' => $provider->getKey(),
        ]);
    }
}
