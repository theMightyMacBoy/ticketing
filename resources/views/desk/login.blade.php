@extends('laravel-ticket::desk.gate')

@section('title', 'Sign in')

@section('content')
    <h1>Sign in</h1>
    <p class="lede">Enter your email. Company addresses continue at the identity provider. Local accounts continue to a password.</p>
    <form class="form-stack" method="post" action="{{ route('laravel-ticket.login.store') }}">
        @csrf
        <label for="email">Email
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </label>
        <button type="submit">Continue</button>
    </form>
@endsection
