<?php

namespace Coderflex\LaravelTicket\Auth;

use Coderflex\LaravelTicket\Models\AuthIdentity;
use Coderflex\LaravelTicket\Models\IdentityProvider;
use Illuminate\Database\Eloquent\Model;

class LoginDiscovery
{
    public function decide(string $email): LoginDecision
    {
        $email = strtolower(trim($email));
        $preference = AuthIdentity::query()->where('email', $email)->first();
        $preference = $preference instanceof AuthIdentity ? $preference : null;
        $provider = IdentityProvider::forEmail($email);

        if ($preference instanceof AuthIdentity && $preference->method === 'local') {
            return new LoginDecision('local');
        }

        if ($provider instanceof IdentityProvider) {
            return new LoginDecision('sso', $provider);
        }

        if ($preference instanceof AuthIdentity && $preference->method === 'sso') {
            $linked = $preference->provider;

            if ($linked instanceof IdentityProvider && $linked->is_active) {
                return new LoginDecision('sso', $linked);
            }

            return new LoginDecision('unknown');
        }

        if ($this->accountExists($email)) {
            return new LoginDecision('local');
        }

        return new LoginDecision('unknown');
    }

    private function accountExists(string $email): bool
    {
        $class = config('auth.providers.users.model');

        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            return false;
        }

        return $class::query()->whereRaw('lower(email) = ?', [$email])->exists();
    }
}
