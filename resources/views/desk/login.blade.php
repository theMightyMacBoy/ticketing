<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sign in</title>
</head>
<body>
    <h1>Sign in</h1>
    <p>Enter your email. Company addresses continue at the identity provider. Local accounts continue to a password.</p>
    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif
    <form method="post" action="{{ route('laravel-ticket.login.store') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
        <button type="submit">Continue</button>
    </form>
</body>
</html>
