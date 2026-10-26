<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Service desk' }}</title>
    <style>
        body { font-family: Georgia, serif; margin: 0; color: #1c1917; background: #fafaf9; }
        header { background: #1c1917; color: #fafaf9; padding: 16px 24px; }
        nav a { color: #fafaf9; margin-right: 16px; }
        main { padding: 24px; max-width: 960px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #e7e5e4; vertical-align: top; }
        label { display: block; margin-top: 12px; }
        input, textarea, select { width: 100%; max-width: 420px; padding: 8px; }
        button { margin-top: 12px; padding: 8px 14px; }
        .error { color: #9f1239; }
        section { margin-top: 32px; }
    </style>
</head>
<body>
<header>
    <strong>Service desk</strong>
    <nav>
        <a href="{{ route('laravel-ticket.desk') }}">Queue</a>
        <a href="{{ route('laravel-ticket.catalog') }}">Catalog</a>
        <a href="{{ route('laravel-ticket.portal') }}">Portal</a>
        <a href="{{ route('laravel-ticket.setup') }}">Setup</a>
        <a href="{{ route('laravel-ticket.directory') }}">Directory</a>
        <a href="{{ route('laravel-ticket.privacy') }}">Privacy</a>
    </nav>
</header>
<main>
    @if (session('status'))
        <p>{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <ul class="error">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif
    @yield('content')
</main>
</body>
</html>
