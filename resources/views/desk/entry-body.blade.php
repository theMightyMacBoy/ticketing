@if ($message->format === 'html')
    <div class="body is-rich">{!! app(\Coderflex\LaravelTicket\RichText\HtmlBody::class)->render((string) $message->message, (int) $ticket->getKey()) !!}</div>
@elseif ((string) $message->message !== '')
    <p class="body">{{ $message->message }}</p>
@endif
@php
    $files = $message->attachments->filter(function ($file) {
        return ! $file->is_inline;
    });
@endphp
@if ($files->isNotEmpty())
    <ul class="files">
        @foreach ($files as $file)
            <li><a href="{{ route('laravel-ticket.tickets.attachments.show', ['ticket' => $ticket, 'attachment' => $file]) }}">{{ $file->original_name }}</a></li>
        @endforeach
    </ul>
@endif
