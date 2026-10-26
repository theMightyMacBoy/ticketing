@extends('laravel-ticket::desk.layout')

@section('content')
    <h1>{{ $ticket->ticket_number }} {{ $ticket->title }}</h1>
    <p>{{ $ticket->message }}</p>
    @foreach ($messages as $message)
        <article>
            <p>{{ $message->message }}</p>
        </article>
    @endforeach
    <form method="post" action="{{ route('laravel-ticket.portal.tickets.reply', $ticket) }}">
        @csrf
        <label for="message">Reply</label>
        <textarea id="message" name="message" required></textarea>
        <button type="submit">Send reply</button>
    </form>
@endsection
