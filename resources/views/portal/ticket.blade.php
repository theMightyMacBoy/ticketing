@extends('laravel-ticket::desk.layout')

@section('title', $ticket->ticket_number.' '.$ticket->title)

@section('content')
    <p class="ticket-no mono">{{ $ticket->ticket_number }}</p>
    <h1>{{ $ticket->title }}</h1>
    <ul class="facts">
        <li>@include('laravel-ticket::desk.state', ['status' => $ticket->status])</li>
        @if ($ticket->is_confidential)
            <li class="flag">Confidential</li>
        @endif
    </ul>
    @if ($ticket->message)
        <article class="entry">
            <header>
                <span class="avatar" aria-hidden="true">{{ strtoupper(mb_substr((string) ($ticket->requester_email ?: 'R'), 0, 1)) }}</span>
                <span class="entry-who">
                    <strong>{{ $ticket->requester_email ?: 'Requester' }}</strong>
                    <span class="channel">Public</span>
                </span>
            </header>
            <p class="body">{{ $ticket->message }}</p>
        </article>
    @endif
    @foreach ($messages as $message)
        @php
            $speaker = $message->displayName();
        @endphp
        <article class="entry">
            <header>
                <span class="avatar" aria-hidden="true">{{ strtoupper(mb_substr($speaker, 0, 1)) }}</span>
                <span class="entry-who">
                    <strong>{{ $speaker }}</strong>
                    <span class="channel">Public</span>
                </span>
                @if ($message->created_at)
                    <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->timezone(config('app.timezone'))->format('M j, Y g:i a') }}</time>
                @endif
            </header>
            @include('laravel-ticket::desk.entry-body', ['message' => $message, 'ticket' => $ticket])
        </article>
    @endforeach
    <section class="composer">
        <h2>Reply</h2>
        <form class="form-stack" method="post" action="{{ route('laravel-ticket.portal.tickets.reply', $ticket) }}" enctype="multipart/form-data">
            @csrf
            @include('laravel-ticket::desk.composer', ['id' => 'message', 'label' => 'Reply', 'ticket' => $ticket])
            <button type="submit">Send reply</button>
        </form>
    </section>
@endsection
