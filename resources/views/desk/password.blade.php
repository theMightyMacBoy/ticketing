<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Password</title>
</head>
<body>
    <h1>Password</h1>
    <p>Enter the password for {{ $email }}.</p>
    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif
    <form method="post" action="{{ route('laravel-ticket.login.store') }}">
        @csrf
        <input type="hidden" name="email" value="{{ $email }}">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autofocus>
        <button type="submit">Sign in</button>
    </form>
    <p><a href="{{ route('laravel-ticket.login') }}">Use a different email</a></p>
</body>
</html>
