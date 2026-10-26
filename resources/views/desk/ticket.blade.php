@extends('laravel-ticket::desk.layout')

@section('content')
    <h1>{{ $ticket->ticket_number }} {{ $ticket->title }}</h1>
    <p>{{ $ticket->status }} · {{ $ticket->priority }}
        @if ($ticket->is_confidential)
            · Confidential
        @endif
    </p>
    <p>{{ $ticket->message }}</p>

    @foreach ($messages as $message)
        <article>
            <p>{{ $message->visibility }} · {{ $message->sender_email }}</p>
            <p>{{ $message->message }}</p>
        </article>
    @endforeach

    @if ($canUpdate)
        <section>
            <h2>Public reply</h2>
            <form method="post" action="{{ route('laravel-ticket.tickets.reply', $ticket) }}">
                @csrf
                <label for="reply">Reply</label>
                <textarea id="reply" name="message" required></textarea>
                <button type="submit">Send reply</button>
            </form>
        </section>
        <section>
            <h2>Assign</h2>
            <form method="post" action="{{ route('laravel-ticket.tickets.assign', $ticket) }}">
                @csrf
                <label for="email">Assignee email</label>
                <input id="email" name="email" type="email" required>
                <button type="submit">Assign</button>
            </form>
        </section>
        <section>
            <h2>Transfer</h2>
            <form method="post" action="{{ route('laravel-ticket.tickets.transfer', $ticket) }}">
                @csrf
                <label for="department_id">Department</label>
                <select id="department_id" name="department_id">
                    <option value="">Keep</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
                <label for="assignment_group_id">Support group</label>
                <select id="assignment_group_id" name="assignment_group_id">
                    <option value="">Keep</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>
                <button type="submit">Transfer</button>
            </form>
        </section>
        <section>
            <h2>Status</h2>
            <form method="post" action="{{ route('laravel-ticket.tickets.status', $ticket) }}">
                @csrf
                <label for="status">Status</label>
                <select id="status" name="status">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @if ($ticket->status === $status->value) selected @endif>{{ $status->value }}</option>
                    @endforeach
                </select>
                <button type="submit">Update status</button>
            </form>
        </section>
    @endif

    @if ($canNote)
        <section>
            <h2>Internal note</h2>
            <form method="post" action="{{ route('laravel-ticket.tickets.note', $ticket) }}">
                @csrf
                <label for="note">Note</label>
                <textarea id="note" name="message" required></textarea>
                <button type="submit">Save note</button>
            </form>
        </section>
    @endif

    @if ($canApprove)
        <section>
            <h2>Approval</h2>
            <form method="post" action="{{ route('laravel-ticket.tickets.approve', $ticket) }}">
                @csrf
                <button type="submit" name="decision" value="approved">Approve</button>
                <button type="submit" name="decision" value="rejected">Reject</button>
            </form>
        </section>
    @endif
@endsection
