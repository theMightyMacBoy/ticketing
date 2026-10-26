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
        <p class="brief">{{ $ticket->message }}</p>
    @endif
    @foreach ($messages as $message)
        <article class="entry">
            <header>
                <span>{{ $message->sender_name ?: $message->sender_email }}</span>
                @if ($message->created_at)
                    <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->timezone(config('app.timezone'))->format('M j, Y g:i a') }}</time>
                @endif
            </header>
            <p class="body">{{ $message->message }}</p>
        </article>
    @endforeach
    <section class="composer">
        <h2>Reply</h2>
        <form class="form-stack" method="post" action="{{ route('laravel-ticket.portal.tickets.reply', $ticket) }}">
            @csrf
            <label for="message">Reply
                <textarea id="message" name="message" required></textarea>
            </label>
            <button type="submit">Send reply</button>
        </form>
    </section>
@endsection
