<?php

use Coderflex\LaravelTicket\Http\Controllers\CatalogController;
use Coderflex\LaravelTicket\Http\Controllers\DirectoryController;
use Coderflex\LaravelTicket\Http\Controllers\PortalController;
use Coderflex\LaravelTicket\Http\Controllers\PrivacyController;
use Coderflex\LaravelTicket\Http\Controllers\QueueController;
use Coderflex\LaravelTicket\Http\Controllers\SetupController;
use Coderflex\LaravelTicket\Http\Controllers\TeamController;
use Coderflex\LaravelTicket\Http\Controllers\TicketController;
use Coderflex\LaravelTicket\Http\DeskAuthRoutes;
use Illuminate\Support\Facades\Route;

$middleware = config('laravel_ticket.routes.middleware', ['web', 'auth']);
$desk = trim((string) config('laravel_ticket.routes.desk', 'desk'), '/');
$portal = trim((string) config('laravel_ticket.routes.portal', 'portal'), '/');

if (! is_array($middleware)) {
    $middleware = ['web', 'auth'];
}

Route::middleware($middleware)->group(function () use ($desk, $portal) {
    Route::get($desk, [QueueController::class, 'index'])->name('laravel-ticket.desk');
    Route::get($desk.'/tickets/{ticket}', [TicketController::class, 'show'])->name('laravel-ticket.tickets.show');
    Route::post($desk.'/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('laravel-ticket.tickets.reply');
    Route::post($desk.'/tickets/{ticket}/note', [TicketController::class, 'note'])->name('laravel-ticket.tickets.note');
    Route::post($desk.'/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('laravel-ticket.tickets.assign');
    Route::post($desk.'/tickets/{ticket}/transfer', [TicketController::class, 'transfer'])->name('laravel-ticket.tickets.transfer');
    Route::post($desk.'/tickets/{ticket}/status', [TicketController::class, 'transition'])->name('laravel-ticket.tickets.status');
    Route::post($desk.'/tickets/{ticket}/approve', [TicketController::class, 'approve'])->name('laravel-ticket.tickets.approve');

    Route::get($desk.'/team', [TeamController::class, 'opened'])->name('laravel-ticket.team');
    Route::get($desk.'/team/open', [TeamController::class, 'open'])->name('laravel-ticket.team.open');

    Route::get($desk.'/catalog', [CatalogController::class, 'create'])->name('laravel-ticket.catalog');
    Route::post($desk.'/catalog', [CatalogController::class, 'store'])->name('laravel-ticket.catalog.store');

    Route::get($desk.'/setup', [SetupController::class, 'index'])->name('laravel-ticket.setup');
    Route::post($desk.'/setup/departments', [SetupController::class, 'storeDepartment'])->name('laravel-ticket.setup.departments');
    Route::post($desk.'/setup/groups', [SetupController::class, 'storeGroup'])->name('laravel-ticket.setup.groups');
    Route::post($desk.'/setup/groups/{group}/managers', [SetupController::class, 'updateGroupManagers'])->name('laravel-ticket.setup.groups.managers');
    Route::post($desk.'/setup/types', [SetupController::class, 'storeType'])->name('laravel-ticket.setup.types');
    Route::post($desk.'/setup/services', [SetupController::class, 'storeService'])->name('laravel-ticket.setup.services');
    Route::post($desk.'/setup/calendars', [SetupController::class, 'storeCalendar'])->name('laravel-ticket.setup.calendars');
    Route::post($desk.'/setup/mailboxes', [SetupController::class, 'storeMailbox'])->name('laravel-ticket.setup.mailboxes');
    Route::post($desk.'/setup/providers', [SetupController::class, 'storeIdentity'])->name('laravel-ticket.setup.providers');

    Route::get($desk.'/directory', [DirectoryController::class, 'index'])->name('laravel-ticket.directory');
    Route::post($desk.'/directory', [DirectoryController::class, 'store'])->name('laravel-ticket.directory.store');

    Route::get($desk.'/privacy', [PrivacyController::class, 'index'])->name('laravel-ticket.privacy');
    Route::get($desk.'/privacy/export', [PrivacyController::class, 'export'])->name('laravel-ticket.privacy.export');
    Route::post($desk.'/privacy/erase', [PrivacyController::class, 'erase'])->name('laravel-ticket.privacy.erase');

    Route::get($portal, [PortalController::class, 'index'])->name('laravel-ticket.portal');
    Route::get($portal.'/tickets/{ticket}', [PortalController::class, 'show'])->name('laravel-ticket.portal.tickets.show');
    Route::post($portal.'/tickets/{ticket}/reply', [PortalController::class, 'reply'])->name('laravel-ticket.portal.tickets.reply');
});

if (config('laravel_ticket.demo.login') || config('laravel_ticket.auth.login')) {
    DeskAuthRoutes::register($desk);
}
