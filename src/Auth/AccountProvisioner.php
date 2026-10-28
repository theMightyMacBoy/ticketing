<?php

namespace Coderflex\LaravelTicket\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class AccountProvisioner
{
    public function findOrCreate(string $email, string $name): Model
    {
        $class = config('auth.providers.users.model');

        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            throw new RuntimeException('The auth user model is not configured.');
        }

        $email = strtolower(trim($email));
        $existing = $class::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($existing instanceof Model) {
            return $existing;
        }

        /** @var Model $user */
        $user = new $class;
        $plain = Str::random(40);
        $casts = $user->getCasts();
        $user->forceFill([
            'name' => $name !== '' ? $name : $email,
            'email' => $email,
            'password' => ($casts['password'] ?? null) === 'hashed' ? $plain : Hash::make($plain),
        ])->save();

        return $user;
    }
}
