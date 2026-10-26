@extends('laravel-ticket::desk.layout')

@section('content')
    <h1>Queue</h1>
    <form method="get" action="{{ route('laravel-ticket.desk') }}">
        <label for="q">Search title, number, or requester</label>
        <input id="q" name="q" value="{{ $search }}">
        <button type="submit">Search</button>
    </form>
    <table>
        <thead>
            <tr>
                <th>Number</th>
                <th>Title</th>
                <th>Department</th>
                <th>Status</th>
                <th>Priority</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tickets as $ticket)
                <tr>
                    <td><a href="{{ route('laravel-ticket.tickets.show', $ticket) }}">{{ $ticket->ticket_number }}</a></td>
                    <td>
                        {{ $ticket->title }}
                        @if ($ticket->is_confidential)
                            <span>Confidential</span>
                        @endif
                    </td>
                    <td>{{ optional($ticket->department)->name }}</td>
                    <td>{{ $ticket->status }}</td>
                    <td>{{ $ticket->priority }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No tickets in this queue.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if ($tickets->previousPageUrl())
        <a href="{{ $tickets->previousPageUrl() }}">Previous</a>
    @endif
    @if ($tickets->nextPageUrl())
        <a href="{{ $tickets->nextPageUrl() }}">Next</a>
    @endif
@endsection
