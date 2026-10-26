<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Sign in')</title>
    @include('laravel-ticket::desk.styles')
</head>
<body class="gate">
<a class="skip" href="#content">Skip to content</a>
<div class="gate-split">
    <aside class="gate-aside">
        <p class="mark">Desk</p>
        <p class="gate-title">Service desk</p>
        <p>One queue for every department. Your email address decides whether you continue at your company sign-in or with a local password.</p>
    </aside>
    <main class="gate-main" id="content">
        <div class="gate-panel">
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
        </div>
    </main>
</div>
</body>
</html>
