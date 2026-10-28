@extends('laravel-ticket::desk.layout')

@section('title', $onlyOpen ? 'Open tickets by my team' : 'Tickets opened by my team')

@section('content')
    <div class="page-head">
        <div>
            <p class="kicker">Team</p>
            <h1>{{ $onlyOpen ? 'Open tickets by my team' : 'Tickets opened by my team' }}</h1>
            <p class="lede">
                @if ($onlyOpen)
                    Tickets your direct reports filed that are still open. Resolved and closed tickets stay on the full list.
                @else
                    Every ticket filed by someone whose manager was synced to your email.
                @endif
            </p>
            @if ($hasReports)
                <p class="count">{{ $tickets->total() }} {{ \Illuminate\Support\Str::plural('ticket', $tickets->total()) }}</p>
            @endif
        </div>
        <form class="finder" method="get" action="{{ $onlyOpen ? route('laravel-ticket.team.open') : route('laravel-ticket.team') }}">
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
                    <th>Requester</th>
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
                        <td>{{ $ticket->requester_email }}</td>
                        <td>{{ optional($ticket->department)->name }}</td>
                        <td>@include('laravel-ticket::desk.state', ['status' => $ticket->status])</td>
                        <td class="priority-{{ $ticket->priority }}">{{ \Illuminate\Support\Str::of((string) $ticket->priority)->replace('_', ' ')->title() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            @if (! $hasReports)
                                No one is recorded as reporting to you. A person's manager is stored when they sign in with single sign-on.
                            @elseif ($onlyOpen)
                                Your team has no open tickets.
                            @else
                                Your team has not opened a ticket.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('laravel-ticket::desk.pager')
@endsection
