<?php

use Coderflex\LaravelTicket\Auth\OidcToken;
use Coderflex\LaravelTicket\Exceptions\SingleSignOnFailed;
use Coderflex\LaravelTicket\Http\DeskAuthRoutes;
use Coderflex\LaravelTicket\Models\AuthIdentity;
use Coderflex\LaravelTicket\Models\IdentityProvider;
use Coderflex\LaravelTicket\Tests\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

function ssoRoutes(): void
{
    DeskAuthRoutes::register('desk');

    $middleware = array_values(array_filter([
        class_exists(ValidateCsrfToken::class) ? ValidateCsrfToken::class : null,
        class_exists(VerifyCsrfToken::class) ? VerifyCsrfToken::class : null,
    ]));

    test()->withoutMiddleware($middleware);
}

/**
 * @return array{key: OpenSSLAsymmetricKey, n: string, e: string}
 */
function ssoKey(): array
{
    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    $details = openssl_pkey_get_details($key);

    return [
        'key' => $key,
        'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
        'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
    ];
}

function ssoJwt(OpenSSLAsymmetricKey $key, array $claims, string $kid = 'test-key'): string
{
    $header = rtrim(strtr(base64_encode((string) json_encode(['alg' => 'RS256', 'kid' => $kid, 'typ' => 'JWT'])), '+/', '-_'), '=');
    $payload = rtrim(strtr(base64_encode((string) json_encode($claims)), '+/', '-_'), '=');
    $input = $header.'.'.$payload;
    openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256);

    return $input.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
}

function ssoProvider(): IdentityProvider
{
    return IdentityProvider::query()->create([
        'name' => 'Contoso',
        'slug' => 'contoso',
        'domains' => ['contoso.com'],
        'issuer' => 'https://login.example.test',
        'client_id' => 'client-1',
        'client_secret' => 'secret-1',
        'is_active' => true,
    ]);
}

function ssoDiscovery(array $jwks): void
{
    Http::fake([
        'https://login.example.test/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://login.example.test',
            'authorization_endpoint' => 'https://login.example.test/authorize',
            'token_endpoint' => 'https://login.example.test/token',
            'jwks_uri' => 'https://login.example.test/jwks',
        ]),
        'https://login.example.test/jwks' => Http::response($jwks),
    ]);
}

it('verifies an rs256 identity token and rejects a bad signature', function () {
    $key = ssoKey();
    $claims = [
        'iss' => 'https://login.example.test',
        'aud' => 'client-1',
        'nonce' => 'nonce-1',
        'exp' => time() + 600,
        'sub' => 'user-1',
        'email' => 'ada@contoso.com',
    ];
    $jwt = ssoJwt($key['key'], $claims);
    $jwks = ['keys' => [[
        'kty' => 'RSA',
        'kid' => 'test-key',
        'n' => $key['n'],
        'e' => $key['e'],
    ]]];
    $tokens = new OidcToken;

    expect($tokens->verify($jwt, $jwks, 'https://login.example.test', 'client-1', 'nonce-1')['email'])->toBe('ada@contoso.com');

    $broken = substr($jwt, 0, -2).'aa';

    expect(fn () => $tokens->verify($broken, $jwks, 'https://login.example.test', 'client-1', 'nonce-1'))
        ->toThrow(SingleSignOnFailed::class);
});

it('sends a company email to the identity provider and a local account to a password', function () {
    ssoRoutes();
    ssoProvider();
    ssoDiscovery(['keys' => []]);
    User::factory()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('password'),
    ]);

    $company = $this->post('/desk/login', ['email' => 'ada@contoso.com']);
    $location = (string) $company->headers->get('Location');

    $company->assertRedirect();
    expect($location)->toContain('https://login.example.test/authorize')
        ->and($location)->toContain('login_hint=ada%40contoso.com')
        ->and($location)->toContain('code_challenge_method=S256')
        ->and($location)->not->toContain('secret-1');

    $this->post('/desk/login', ['email' => 'ada@example.com'])
        ->assertRedirect(route('laravel-ticket.login.password'));

    $this->post('/desk/login', [
        'email' => 'ada@example.com',
        'password' => 'password',
    ])->assertRedirect(route('laravel-ticket.desk'));
});

it('keeps a local override on a single sign-on domain', function () {
    ssoRoutes();
    ssoProvider();
    User::factory()->create([
        'email' => 'breakglass@contoso.com',
        'password' => Hash::make('password'),
    ]);
    AuthIdentity::query()->create([
        'email' => 'breakglass@contoso.com',
        'method' => 'local',
    ]);

    $this->post('/desk/login', ['email' => 'breakglass@contoso.com'])
        ->assertRedirect(route('laravel-ticket.login.password'));
});

it('signs in a new requester from the identity provider and rejects a mismatched email', function () {
    ssoRoutes();
    ssoProvider();
    $key = ssoKey();
    $jwks = ['keys' => [[
        'kty' => 'RSA',
        'kid' => 'test-key',
        'n' => $key['n'],
        'e' => $key['e'],
    ]]];
    $claims = [
        'iss' => 'https://login.example.test',
        'aud' => 'client-1',
        'nonce' => 'nonce-1',
        'exp' => time() + 600,
        'sub' => 'ada-subject',
        'email' => 'ada@contoso.com',
        'name' => 'Ada Lovelace',
    ];

    Http::fake([
        'https://login.example.test/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://login.example.test',
            'authorization_endpoint' => 'https://login.example.test/authorize',
            'token_endpoint' => 'https://login.example.test/token',
            'jwks_uri' => 'https://login.example.test/jwks',
        ]),
        'https://login.example.test/token' => Http::response([
            'id_token' => ssoJwt($key['key'], $claims),
            'token_type' => 'Bearer',
        ]),
        'https://login.example.test/jwks' => Http::response($jwks),
    ]);

    $this->withSession([
        'laravel-ticket.sso' => [
            'state' => 'state-1',
            'nonce' => 'nonce-1',
            'verifier' => 'verifier-1',
            'provider' => 'contoso',
            'email' => 'ada@contoso.com',
        ],
    ])->get('/desk/login/sso/contoso/callback?code=auth-code&state=state-1')
        ->assertRedirect(route('laravel-ticket.desk'));

    $this->get(route('laravel-ticket.desk'))->assertOk();

    expect(User::query()->where('email', 'ada@contoso.com')->value('name'))->toBe('Ada Lovelace')
        ->and(AuthIdentity::query()->where('email', 'ada@contoso.com')->value('subject'))->toBe('ada-subject');

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/token')
            && $request['code'] === 'auth-code'
            && $request['code_verifier'] === 'verifier-1'
            && $request['client_id'] === 'client-1';
    });
});

it('rejects an identity token for a different email address', function () {
    ssoRoutes();
    ssoProvider();
    $key = ssoKey();
    $claims = [
        'iss' => 'https://login.example.test',
        'aud' => 'client-1',
        'nonce' => 'nonce-1',
        'exp' => time() + 600,
        'sub' => 'other-subject',
        'email' => 'other@contoso.com',
        'name' => 'Someone Else',
    ];

    Http::fake([
        'https://login.example.test/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://login.example.test',
            'authorization_endpoint' => 'https://login.example.test/authorize',
            'token_endpoint' => 'https://login.example.test/token',
            'jwks_uri' => 'https://login.example.test/jwks',
        ]),
        'https://login.example.test/token' => Http::response([
            'id_token' => ssoJwt($key['key'], $claims),
        ]),
        'https://login.example.test/jwks' => Http::response(['keys' => [[
            'kty' => 'RSA',
            'kid' => 'test-key',
            'n' => $key['n'],
            'e' => $key['e'],
        ]]]),
    ]);

    $this->withSession([
        'laravel-ticket.sso' => [
            'state' => 'state-2',
            'nonce' => 'nonce-1',
            'verifier' => 'verifier-1',
            'provider' => 'contoso',
            'email' => 'ada@contoso.com',
        ],
    ])->get('/desk/login/sso/contoso/callback?code=auth-code&state=state-2')
        ->assertRedirect(route('laravel-ticket.login'))
        ->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'other@contoso.com')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'ada@contoso.com')->exists())->toBeFalse();
});

it('does not reveal the client secret when the token request fails', function () {
    ssoRoutes();
    ssoProvider();
    Http::fake([
        'https://login.example.test/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://login.example.test',
            'authorization_endpoint' => 'https://login.example.test/authorize',
            'token_endpoint' => 'https://login.example.test/token',
            'jwks_uri' => 'https://login.example.test/jwks',
        ]),
        'https://login.example.test/token' => Http::response([
            'error' => 'invalid_client',
            'error_description' => 'secret-1 was rejected',
        ], 401),
    ]);

    $response = $this->withSession([
        'laravel-ticket.sso' => [
            'state' => 'state-1',
            'nonce' => 'nonce-1',
            'verifier' => 'verifier-1',
            'provider' => 'contoso',
            'email' => 'ada@contoso.com',
        ],
    ])->get('/desk/login/sso/contoso/callback?code=auth-code&state=state-1');

    $response->assertRedirect(route('laravel-ticket.login'));
    expect(session('errors')->first('email'))->toContain('invalid_client')
        ->and(session('errors')->first('email'))->not->toContain('secret-1');
});

it('refuses an unknown email that has no sign-in provider', function () {
    ssoRoutes();

    $this->from('/desk/login')
        ->post('/desk/login', ['email' => 'nobody@example.net'])
        ->assertRedirect('/desk/login')
        ->assertSessionHasErrors('email');

    expect(User::query()->count())->toBe(0);
});

it('ignores a password posted for a single sign-on address', function () {
    ssoRoutes();
    ssoProvider();
    ssoDiscovery(['keys' => []]);
    User::factory()->create([
        'email' => 'ada@contoso.com',
        'password' => Hash::make('password'),
    ]);

    $response = $this->post('/desk/login', [
        'email' => 'ada@contoso.com',
        'password' => 'password',
    ]);

    $response->assertRedirect();
    expect((string) $response->headers->get('Location'))->toContain('https://login.example.test/authorize');
    $this->assertGuest();
});

it('saves a sign-in provider from setup without showing the secret', function () {
    ssoRoutes();
    config()->set('laravel_ticket.agents.callback', fn () => true);
    $agent = User::factory()->create(['email' => 'it.agent@example.com']);

    $this->actingAs($agent)
        ->post(route('laravel-ticket.setup.providers'), [
            'name' => 'Contoso',
            'domain' => 'contoso.com',
            'issuer' => 'https://login.microsoftonline.com/tenant/v2.0',
            'client_id' => 'client-1',
            'client_secret' => 'secret-1',
        ])
        ->assertRedirect();

    $page = $this->actingAs($agent)->get(route('laravel-ticket.setup'));

    $page->assertOk()->assertSee('contoso.com')->assertDontSee('secret-1');
    expect(IdentityProvider::query()->where('slug', 'contoso')->first()->client_secret)->toBe('secret-1');
});
