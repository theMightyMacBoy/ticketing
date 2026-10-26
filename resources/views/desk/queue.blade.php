@extends('laravel-ticket::desk.layout')

@section('title', 'Queue')

@section('content')
    <div class="page-head">
        <div>
            <p class="kicker">Queue</p>
            <h1>Open work</h1>
            <p class="count">{{ $tickets->total() }} {{ \Illuminate\Support\Str::plural('ticket', $tickets->total()) }}</p>
        </div>
        <form class="finder" method="get" action="{{ route('laravel-ticket.desk') }}">
            <label for="q">Search title, number, or requester</label>
            <div class="finder-row">
                <input id="q" name="q" value="{{ $search }}" placeholder="Number, title, or requester">
                <button type="submit">Search</button>
            </div>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Number</th>
                    <th>Request</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Priority</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    <tr>
                        <td><a class="mono" href="{{ route('laravel-ticket.tickets.show', $ticket) }}">{{ $ticket->ticket_number }}</a></td>
                        <td>
                            <div class="title-line">
                                <a class="title" href="{{ route('laravel-ticket.tickets.show', $ticket) }}">{{ $ticket->title }}</a>
                                @if ($ticket->is_confidential)
                                    <span class="flag">Confidential</span>
                                @endif
                            </div>
                        </td>
                        <td>{{ optional($ticket->department)->name }}</td>
                        <td>@include('laravel-ticket::desk.state', ['status' => $ticket->status])</td>
                        <td class="priority-{{ $ticket->priority }}">{{ \Illuminate\Support\Str::of((string) $ticket->priority)->replace('_', ' ')->title() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No tickets in this queue.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('laravel-ticket::desk.pager')
@endsection
