@extends('laravel-ticket::desk.gate')

@section('title', 'Password')

@section('content')
    <h1>Password</h1>
    <p class="lede">Enter the password for {{ $email }}.</p>
    <form class="form-stack" method="post" action="{{ route('laravel-ticket.login.store') }}">
        @csrf
        <input type="hidden" name="email" value="{{ $email }}">
        <label for="password">Password
            <input id="password" name="password" type="password" required autofocus autocomplete="current-password">
        </label>
        <button type="submit">Sign in</button>
    </form>
    <a class="inline-link" href="{{ route('laravel-ticket.login') }}">Use a different email</a>
@endsection
