@extends('laravel-ticket::desk.layout')

@section('content')
    <h1>My tickets</h1>
    <ul>
        @forelse ($tickets as $ticket)
            <li>
                <a href="{{ route('laravel-ticket.portal.tickets.show', $ticket) }}">{{ $ticket->ticket_number }} {{ $ticket->title }}</a>
            </li>
        @empty
            <li>You have no tickets.</li>
        @endforelse
    </ul>
    @if ($tickets->previousPageUrl())
        <a href="{{ $tickets->previousPageUrl() }}">Previous</a>
    @endif
    @if ($tickets->nextPageUrl())
        <a href="{{ $tickets->nextPageUrl() }}">Next</a>
    @endif
    <p><a href="{{ route('laravel-ticket.catalog') }}">Open a request</a></p>
@endsection
