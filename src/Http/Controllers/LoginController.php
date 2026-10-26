<?php

namespace Coderflex\LaravelTicket\Http\Controllers;

use Coderflex\LaravelTicket\Auth\LoginDiscovery;
use Coderflex\LaravelTicket\Auth\OidcClient;
use Coderflex\LaravelTicket\Exceptions\SingleSignOnFailed;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController
{
    public const EMAIL = 'laravel-ticket.login.email';

    public function create(): View
    {
        return view('laravel-ticket::desk.login');
    }

    public function password(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get(self::EMAIL);

        if (! is_string($email) || $email === '') {
            return redirect()->route('laravel-ticket.login');
        }

        return view('laravel-ticket::desk.password', ['email' => $email]);
    }

    public function store(Request $request, LoginDiscovery $discovery, OidcClient $oidc): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['nullable', 'string'],
        ]);

        $email = strtolower($data['email']);
        $sessionEmail = $request->session()->get(self::EMAIL);
        $password = $data['password'] ?? null;

        if (is_string($sessionEmail) && $sessionEmail !== '' && is_string($password) && $password !== '' && $email !== strtolower($sessionEmail)) {
            return back()->withErrors(['email' => 'Start again with your email address.'])->onlyInput('email');
        }

        try {
            $decision = $discovery->decide($email);
        } catch (SingleSignOnFailed $exception) {
            return back()->withErrors(['email' => $exception->getMessage()])->onlyInput('email');
        }

        if ($decision->method === 'unknown') {
            return back()->withErrors(['email' => 'That email is not registered.'])->onlyInput('email');
        }

        if ($decision->method === 'sso') {
            if ($decision->provider === null) {
                return back()->withErrors(['email' => 'Single sign-on is not configured for that email.'])->onlyInput('email');
            }

            try {
                return $oidc->redirect($request, $decision->provider, $email);
            } catch (SingleSignOnFailed $exception) {
                return back()->withErrors(['email' => $exception->getMessage()])->onlyInput('email');
            }
        }

        if (! is_string($password) || $password === '') {
            $request->session()->put(self::EMAIL, $email);

            return redirect()->route('laravel-ticket.login.password');
        }

        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            return back()->withErrors(['email' => 'Those credentials were not recognized.'])->onlyInput('email');
        }

        $request->session()->forget(self::EMAIL);
        $request->session()->regenerate();

        return redirect()->route('laravel-ticket.desk');
    }

    public function callback(Request $request, string $provider, OidcClient $oidc): RedirectResponse
    {
        try {
            $user = $oidc->authenticate($request, $provider);
        } catch (SingleSignOnFailed $exception) {
            return redirect()->route('laravel-ticket.login')->withErrors(['email' => $exception->getMessage()]);
        }

        if (! $user instanceof Authenticatable) {
            return redirect()->route('laravel-ticket.login')->withErrors(['email' => 'The account could not be signed in.']);
        }

        Auth::login($user);
        $request->session()->forget(self::EMAIL);
        $request->session()->regenerate();

        return redirect()->route('laravel-ticket.desk');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('laravel-ticket.login');
    }
}
