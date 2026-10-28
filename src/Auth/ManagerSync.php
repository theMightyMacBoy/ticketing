<?php

namespace Coderflex\LaravelTicket\Auth;

use Coderflex\LaravelTicket\Models\AuthIdentity;
use Coderflex\LaravelTicket\Models\IdentityProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ManagerSync
{
    /**
     * Store the manager named by the identity token, or by Microsoft Graph
     * when this provider is allowed to look one up. A failed lookup does not
     * fail sign-in and does not clear a manager we already know.
     *
     * @param  array<string, mixed>  $claims
     */
    public function sync(IdentityProvider $provider, string $email, array $claims): void
    {
        $email = strtolower(trim($email));
        $claimed = $this->claim($claims);

        if ($claimed !== null) {
            $this->store($email, $claimed === $email ? null : $claimed);

            return;
        }

        if (! $provider->sync_manager) {
            return;
        }

        $tenant = $this->tenant($provider);

        if ($tenant === null) {
            return;
        }

        try {
            $looked = $this->graph($provider, $tenant, $email, $claims);
        } catch (Throwable) {
            return;
        }

        if (! $looked['confirmed']) {
            return;
        }

        $manager = $looked['email'];
        $this->store($email, $manager === $email ? null : $manager);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function claim(array $claims): ?string
    {
        $candidates = [
            $claims['manager_email'] ?? null,
        ];
        $manager = $claims['manager'] ?? null;

        if (is_string($manager)) {
            $candidates[] = $manager;
        }

        if (is_array($manager)) {
            $candidates[] = $manager['email'] ?? null;
            $candidates[] = $manager['mail'] ?? null;
            $candidates[] = $manager['userPrincipalName'] ?? null;
        }

        foreach ($candidates as $candidate) {
            $email = $this->email($candidate);

            if ($email !== null) {
                return $email;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return array{confirmed: bool, email: string|null}
     */
    private function graph(IdentityProvider $provider, string $tenant, string $email, array $claims): array
    {
        $token = $this->token($provider, $tenant);

        if ($token === null) {
            return ['confirmed' => false, 'email' => null];
        }

        $key = $email;
        $oid = $claims['oid'] ?? null;

        if (is_string($oid) && preg_match('/^[A-Za-z0-9-]+$/', $oid) === 1) {
            $key = $oid;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(15)
            ->get('https://graph.microsoft.com/v1.0/users/'.rawurlencode($key).'/manager', [
                '$select' => 'mail,userPrincipalName',
            ]);

        if ($response->status() === 401) {
            Cache::forget($this->tokenKey($provider));
            $token = $this->token($provider, $tenant);

            if ($token === null) {
                return ['confirmed' => false, 'email' => null];
            }

            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->get('https://graph.microsoft.com/v1.0/users/'.rawurlencode($key).'/manager', [
                    '$select' => 'mail,userPrincipalName',
                ]);
        }

        if ($response->status() === 404) {
            return ['confirmed' => true, 'email' => null];
        }

        if ($response->failed()) {
            return ['confirmed' => false, 'email' => null];
        }

        $body = $response->json();
        $manager = $this->email(is_array($body) ? ($body['mail'] ?? null) : null)
            ?? $this->email(is_array($body) ? ($body['userPrincipalName'] ?? null) : null);

        if ($manager === null) {
            return ['confirmed' => false, 'email' => null];
        }

        return ['confirmed' => true, 'email' => $manager];
    }

    private function token(IdentityProvider $provider, string $tenant): ?string
    {
        $cached = Cache::get($this->tokenKey($provider));

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()->acceptJson()->timeout(15)->post(
            'https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0/token',
            [
                'client_id' => $provider->client_id,
                'client_secret' => $provider->client_secret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]
        );

        $token = $response->json('access_token');

        if ($response->failed() || ! is_string($token) || $token === '') {
            return null;
        }

        $ttl = (int) $response->json('expires_in');

        if ($ttl > 120) {
            Cache::put($this->tokenKey($provider), $token, $ttl - 60);
        }

        return $token;
    }

    private function tenant(IdentityProvider $provider): ?string
    {
        $issuer = rtrim($provider->issuer, '/');

        if (preg_match('#^https://login\.microsoftonline\.com/([^/]+)(?:/v2\.0)?$#', $issuer, $matches) !== 1) {
            return null;
        }

        $tenant = $matches[1];

        if (preg_match('/^[A-Za-z0-9.-]+$/', $tenant) !== 1) {
            return null;
        }

        return $tenant;
    }

    private function email(mixed $value): ?string
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return strtolower($value);
    }

    private function store(string $email, ?string $manager): void
    {
        AuthIdentity::query()->where('email', $email)->update([
            'manager_email' => $manager,
        ]);
    }

    private function tokenKey(IdentityProvider $provider): string
    {
        return 'laravel-ticket.graph-token.'.$provider->getKey();
    }
}
