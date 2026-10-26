<?php

namespace Coderflex\LaravelTicket\Auth;

use Coderflex\LaravelTicket\Exceptions\SingleSignOnFailed;
use Coderflex\LaravelTicket\Models\AuthIdentity;
use Coderflex\LaravelTicket\Models\IdentityProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OidcClient
{
    public const SESSION = 'laravel-ticket.sso';

    public function __construct(
        private OidcToken $tokens,
        private AccountProvisioner $accounts,
    ) {}

    public function redirect(Request $request, IdentityProvider $provider, string $email): RedirectResponse
    {
        $document = $this->document($provider);
        $endpoint = $document['authorization_endpoint'] ?? null;

        if (! is_string($endpoint) || ! str_starts_with($endpoint, 'https://')) {
            throw new SingleSignOnFailed('The identity provider did not publish a sign-in address.');
        }

        $verifier = $this->random(64);
        $state = $this->random(32);
        $nonce = $this->random(32);

        $request->session()->put(self::SESSION, [
            'state' => $state,
            'nonce' => $nonce,
            'verifier' => $verifier,
            'provider' => $provider->slug,
            'email' => strtolower($email),
        ]);

        $query = http_build_query([
            'client_id' => $provider->client_id,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri($provider),
            'scope' => 'openid profile email',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $this->challenge($verifier),
            'code_challenge_method' => 'S256',
            'login_hint' => strtolower($email),
        ]);

        $separator = str_contains($endpoint, '?') ? '&' : '?';

        return redirect()->away($endpoint.$separator.$query);
    }

    public function authenticate(Request $request, string $slug): Model
    {
        $pending = $request->session()->pull(self::SESSION);

        if (! is_array($pending) || ($pending['provider'] ?? null) !== $slug) {
            throw new SingleSignOnFailed('This sign-in attempt has expired. Enter your email again.');
        }

        $state = $request->query('state');
        $expected = $pending['state'] ?? null;

        if (! is_string($state) || ! is_string($expected) || ! hash_equals($expected, $state)) {
            throw new SingleSignOnFailed('This sign-in attempt has expired. Enter your email again.');
        }

        $error = $request->query('error');

        if (is_string($error) && $error !== '') {
            $code = preg_match('/^[A-Za-z0-9_]+$/', $error) === 1 ? $error : 'rejected';

            throw new SingleSignOnFailed("The identity provider did not complete sign-in ({$code}).");
        }

        $code = $request->query('code');
        $email = $pending['email'] ?? null;
        $nonce = $pending['nonce'] ?? null;
        $verifier = $pending['verifier'] ?? null;

        if (! is_string($code) || $code === '' || ! is_string($email) || ! is_string($nonce) || ! is_string($verifier)) {
            throw new SingleSignOnFailed('The identity provider did not complete sign-in.');
        }

        $provider = IdentityProvider::query()->where('slug', $slug)->where('is_active', true)->first();

        if (! $provider instanceof IdentityProvider) {
            throw new SingleSignOnFailed('Single sign-on is not configured for that email.');
        }

        $claims = $this->claims($provider, $code, $verifier, $nonce);
        $confirmed = $this->emailFrom($claims);

        if ($confirmed === null || strcasecmp($confirmed, $email) !== 0) {
            throw new SingleSignOnFailed('The identity provider returned a different email address.');
        }

        $subject = $claims['sub'] ?? null;

        if (! is_string($subject) || $subject === '') {
            throw new SingleSignOnFailed('The identity provider did not identify the account.');
        }

        $this->link($provider, $email, $subject);
        $user = $this->accounts->findOrCreate($email, $this->displayName($claims, $email));

        if (! $user instanceof Authenticatable) {
            throw new SingleSignOnFailed('The account could not be signed in.');
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function claims(IdentityProvider $provider, string $code, string $verifier, string $nonce): array
    {
        $document = $this->document($provider);
        $tokenEndpoint = $document['token_endpoint'] ?? null;
        $jwksUri = $document['jwks_uri'] ?? null;
        $issuer = is_string($document['issuer'] ?? null) && $document['issuer'] !== ''
            ? $document['issuer']
            : $provider->issuer;

        if (! is_string($tokenEndpoint) || ! str_starts_with($tokenEndpoint, 'https://') || ! is_string($jwksUri) || ! str_starts_with($jwksUri, 'https://')) {
            throw new SingleSignOnFailed('The identity provider did not publish a token address.');
        }

        if (rtrim((string) $issuer, '/') !== rtrim($provider->issuer, '/')) {
            throw new SingleSignOnFailed('The identity provider published an unexpected issuer.');
        }

        $response = Http::asForm()->acceptJson()->timeout(15)->post($tokenEndpoint, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri($provider),
            'client_id' => $provider->client_id,
            'client_secret' => $provider->client_secret,
            'code_verifier' => $verifier,
        ]);

        $idToken = $response->json('id_token');
        $error = $response->json('error');

        if ($response->failed() || ! is_string($idToken) || $idToken === '') {
            $reason = is_string($error) && preg_match('/^[A-Za-z0-9_]+$/', $error) === 1 ? $error : 'HTTP '.$response->status();

            throw new SingleSignOnFailed("The identity provider rejected the sign-in ({$reason}).");
        }

        $jwks = $this->jwks($provider, $jwksUri);

        try {
            return $this->tokens->verify($idToken, $jwks, (string) $issuer, $provider->client_id, $nonce);
        } catch (SingleSignOnFailed $exception) {
            if (! $exception->retryable) {
                throw $exception;
            }

            Cache::forget($this->jwksKey($provider));

            return $this->tokens->verify($idToken, $this->jwks($provider, $jwksUri), (string) $issuer, $provider->client_id, $nonce);
        }
    }

    private function link(IdentityProvider $provider, string $email, string $subject): void
    {
        $bySubject = AuthIdentity::query()
            ->where('identity_provider_id', $provider->getKey())
            ->where('subject', $subject)
            ->first();

        if ($bySubject instanceof AuthIdentity && strcasecmp($bySubject->email, $email) !== 0) {
            throw new SingleSignOnFailed('That identity is already linked to a different email.');
        }

        $byEmail = AuthIdentity::query()->where('email', $email)->first();

        if (
            $byEmail instanceof AuthIdentity
            && is_string($byEmail->subject)
            && $byEmail->subject !== ''
            && $byEmail->subject !== $subject
        ) {
            throw new SingleSignOnFailed('This email is already linked to a different identity.');
        }

        $method = $byEmail instanceof AuthIdentity && $byEmail->method === 'local' ? 'local' : 'sso';

        AuthIdentity::query()->updateOrCreate(['email' => $email], [
            'method' => $method,
            'identity_provider_id' => $provider->getKey(),
            'subject' => $subject,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function document(IdentityProvider $provider): array
    {
        $cached = Cache::remember($this->documentKey($provider), 3600, function () use ($provider) {
            $url = rtrim($provider->issuer, '/').'/.well-known/openid-configuration';
            $response = Http::acceptJson()->timeout(15)->get($url);
            $body = $response->json();

            if ($response->failed() || ! is_array($body)) {
                return [];
            }

            return $body;
        });

        if ($cached === []) {
            Cache::forget($this->documentKey($provider));

            throw new SingleSignOnFailed('The identity provider configuration could not be read.');
        }

        return $cached;
    }

    /**
     * @return array<string, mixed>
     */
    private function jwks(IdentityProvider $provider, string $url): array
    {
        $cached = Cache::remember($this->jwksKey($provider), 3600, function () use ($url) {
            $response = Http::acceptJson()->timeout(15)->get($url);
            $body = $response->json();

            if ($response->failed() || ! is_array($body)) {
                return [];
            }

            return $body;
        });

        return $cached;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function emailFrom(array $claims): ?string
    {
        foreach (['email', 'preferred_username', 'upn'] as $key) {
            $value = $claims[$key] ?? null;

            if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return strtolower($value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function displayName(array $claims, string $email): string
    {
        if (is_string($claims['name'] ?? null) && $claims['name'] !== '') {
            return $claims['name'];
        }

        $given = is_string($claims['given_name'] ?? null) ? $claims['given_name'] : '';
        $family = is_string($claims['family_name'] ?? null) ? $claims['family_name'] : '';
        $combined = trim($given.' '.$family);

        if ($combined !== '') {
            return $combined;
        }

        $local = strstr($email, '@', true);

        return is_string($local) && $local !== '' ? $local : $email;
    }

    private function redirectUri(IdentityProvider $provider): string
    {
        return route('laravel-ticket.login.callback', ['provider' => $provider->slug]);
    }

    private function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private function random(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    private function documentKey(IdentityProvider $provider): string
    {
        return 'laravel-ticket.oidc.'.$provider->getKey();
    }

    private function jwksKey(IdentityProvider $provider): string
    {
        return 'laravel-ticket.oidc-jwks.'.$provider->getKey();
    }
}
