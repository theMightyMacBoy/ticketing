@extends('laravel-ticket::desk.layout')

@section('title', 'Privacy')

@section('content')
    <div class="page-head">
        <div>
            <p class="kicker">Administration</p>
            <h1>Privacy</h1>
            <p class="lede">Export or erase tickets for an email address. Confidential tickets stay with the department unless you are that requester.</p>
        </div>
    </div>
    <p><a class="button quiet" href="{{ route('laravel-ticket.privacy.export', ['email' => $email]) }}">Export {{ $email }}</a></p>
    <form class="form-stack narrow" method="post" action="{{ route('laravel-ticket.privacy.erase') }}">
        @csrf
        <label for="email">Email
            <input id="email" name="email" type="email" value="{{ $email }}" required>
        </label>
        <button class="danger" type="submit">Erase tickets</button>
    </form>
@endsection
