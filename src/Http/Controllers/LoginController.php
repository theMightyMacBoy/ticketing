<?php

namespace Coderflex\LaravelTicket\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController
{
    public function create(): View
    {
        return view('laravel-ticket::desk.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return back()->withErrors(['email' => 'Those credentials were not recognized.'])->onlyInput('email');
        }

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
