<?php

namespace Coderflex\LaravelTicket\Http;

use Coderflex\LaravelTicket\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

class DeskAuthRoutes
{
    public static function register(string $desk): void
    {
        Route::middleware('web')->group(function () use ($desk) {
            Route::get($desk.'/login', [LoginController::class, 'create'])->name('laravel-ticket.login');
            Route::post($desk.'/login', [LoginController::class, 'store'])->name('laravel-ticket.login.store');
            Route::get($desk.'/login/password', [LoginController::class, 'password'])->name('laravel-ticket.login.password');
            Route::get($desk.'/login/sso/{provider}/callback', [LoginController::class, 'callback'])->name('laravel-ticket.login.callback');
            Route::post($desk.'/logout', [LoginController::class, 'destroy'])
                ->middleware('auth')
                ->name('laravel-ticket.logout');
        });
    }
}
