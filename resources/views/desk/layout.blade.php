<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Service desk')</title>
    @include('laravel-ticket::desk.styles')
</head>
<body>
@php
    $account = auth()->user();
    $accountName = is_object($account) ? (string) $account->getAttribute('name') : '';
    $accountEmail = is_object($account) ? (string) $account->getAttribute('email') : '';
    $canConfigure = $account instanceof \Illuminate\Database\Eloquent\Model
        && \Coderflex\LaravelTicket\Http\DeskAccess::canConfigure($account);
    $nameParts = preg_split('/\s+/', trim($accountName)) ?: [];
    $initials = strtoupper(mb_substr((string) ($nameParts[0] ?? ''), 0, 1));

    if (count($nameParts) > 1) {
        $initials .= strtoupper(mb_substr((string) $nameParts[count($nameParts) - 1], 0, 1));
    }

    $deskNav = 'queue';

    if (request()->routeIs('laravel-ticket.catalog')) {
        $deskNav = 'catalog';
    } elseif (request()->routeIs('laravel-ticket.team.open')) {
        $deskNav = 'team-open';
    } elseif (request()->routeIs('laravel-ticket.team')) {
        $deskNav = 'team';
    } elseif (request()->routeIs('laravel-ticket.portal', 'laravel-ticket.portal.*')) {
        $deskNav = 'portal';
    } elseif (request()->routeIs('laravel-ticket.setup', 'laravel-ticket.setup.*')) {
        $deskNav = 'setup';
    } elseif (request()->routeIs('laravel-ticket.directory', 'laravel-ticket.directory.*')) {
        $deskNav = 'directory';
    } elseif (request()->routeIs('laravel-ticket.privacy', 'laravel-ticket.privacy.*')) {
        $deskNav = 'privacy';
    }
@endphp
<a class="skip" href="#content">Skip to content</a>
<div class="shell">
    <aside class="rail">
        <div>
            <a class="mark" href="{{ route('laravel-ticket.desk') }}">Desk</a>
            <p class="rail-kicker">Service desk</p>
        </div>
        <nav class="rail-nav rail-work" aria-label="Desk">
            <a href="{{ route('laravel-ticket.desk') }}" @if ($deskNav === 'queue') class="is-current" aria-current="page" @endif>Queue</a>
            <a href="{{ route('laravel-ticket.catalog') }}" @if ($deskNav === 'catalog') class="is-current" aria-current="page" @endif>Catalog</a>
            <a href="{{ route('laravel-ticket.portal') }}" @if ($deskNav === 'portal') class="is-current" aria-current="page" @endif>My requests</a>
        </nav>
        <nav class="rail-nav rail-team" aria-label="Team">
            <p class="rail-label">Team</p>
            <a href="{{ route('laravel-ticket.team') }}" @if ($deskNav === 'team') class="is-current" aria-current="page" @endif>Tickets opened by my team</a>
            <a href="{{ route('laravel-ticket.team.open') }}" @if ($deskNav === 'team-open') class="is-current" aria-current="page" @endif>Open tickets by my team</a>
        </nav>
        @if ($canConfigure)
            <nav class="rail-nav rail-admin" aria-label="Administration">
                <p class="rail-label">Administration</p>
                <a href="{{ route('laravel-ticket.setup') }}" @if ($deskNav === 'setup') class="is-current" aria-current="page" @endif>Setup</a>
                <a href="{{ route('laravel-ticket.directory') }}" @if ($deskNav === 'directory') class="is-current" aria-current="page" @endif>Directory</a>
                <a href="{{ route('laravel-ticket.privacy') }}" @if ($deskNav === 'privacy') class="is-current" aria-current="page" @endif>Privacy</a>
            </nav>
        @endif
        <div class="rail-foot">
            @if ($accountName !== '' || $accountEmail !== '')
                <div class="rail-person">
                    @if ($initials !== '')
                        <span class="avatar" aria-hidden="true">{{ $initials }}</span>
                    @endif
                    <div>
                        @if ($accountName !== '')
                            <p class="who">{{ $accountName }}</p>
                        @endif
                        @if ($accountEmail !== '')
                            <p class="who-mail">{{ $accountEmail }}</p>
                        @endif
                    </div>
                </div>
            @endif
            <button class="theme-toggle" type="button" data-theme-toggle>
                <span class="when-light">Dark mode</span>
                <span class="when-dark">Light mode</span>
            </button>
            @if (\Illuminate\Support\Facades\Route::has('laravel-ticket.logout'))
                <form method="post" action="{{ route('laravel-ticket.logout') }}">
                    @csrf
                    <button type="submit">Sign out</button>
                </form>
            @endif
        </div>
    </aside>
    <main class="stage" id="content">
        @if (session('status'))
            <p class="flash" role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div class="flash is-error" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
