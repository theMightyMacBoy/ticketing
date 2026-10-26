@extends('laravel-ticket::desk.layout')

@section('content')
    <h1>Privacy</h1>
    <p>Export or erase tickets for an email address. Confidential tickets stay with the department unless you are that requester.</p>
    <p><a href="{{ route('laravel-ticket.privacy.export', ['email' => $email]) }}">Export {{ $email }}</a></p>
    <form method="post" action="{{ route('laravel-ticket.privacy.erase') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ $email }}" required>
        <button type="submit">Erase tickets</button>
    </form>
@endsection
