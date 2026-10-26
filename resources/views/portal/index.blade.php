@extends('laravel-ticket::desk.layout')

@section('title', 'My requests')

@section('content')
    <div class="page-head">
        <div>
            <p class="kicker">Portal</p>
            <h1>My tickets</h1>
            <p class="count">{{ $tickets->total() }} {{ \Illuminate\Support\Str::plural('ticket', $tickets->total()) }}</p>
        </div>
        <a class="button" href="{{ route('laravel-ticket.catalog') }}">Open a request</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Number</th>
                    <th>Request</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    <tr>
                        <td><a class="mono" href="{{ route('laravel-ticket.portal.tickets.show', $ticket) }}">{{ $ticket->ticket_number }}</a></td>
                        <td><a class="title" href="{{ route('laravel-ticket.portal.tickets.show', $ticket) }}">{{ $ticket->title }}</a></td>
                        <td>@include('laravel-ticket::desk.state', ['status' => $ticket->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="3">You have no tickets.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('laravel-ticket::desk.pager')
@endsection
