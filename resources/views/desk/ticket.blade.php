@extends('laravel-ticket::desk.layout')

@section('title', $ticket->ticket_number.' '.$ticket->title)

@section('content')
    <div class="ticket-layout">
        <div>
            <p class="ticket-no mono">{{ $ticket->ticket_number }}</p>
            <h1>{{ $ticket->title }}</h1>
            <ul class="facts">
                <li>@include('laravel-ticket::desk.state', ['status' => $ticket->status])</li>
                <li class="priority-{{ $ticket->priority }}">{{ \Illuminate\Support\Str::of((string) $ticket->priority)->replace('_', ' ')->title() }}</li>
                @if (optional($ticket->department)->name)
                    <li>{{ $ticket->department->name }}</li>
                @endif
                @if ($ticket->requester_email)
                    <li>{{ $ticket->requester_email }}</li>
                @endif
                @if ($ticket->is_confidential)
                    <li class="flag">Confidential</li>
                @endif
            </ul>
            @if ($ticket->message)
                <p class="brief">{{ $ticket->message }}</p>
            @endif

            @foreach ($messages as $message)
                <article class="entry @if ($message->visibility !== 'public') is-internal @endif">
                    <header>
                        <span>{{ $message->visibility === 'public' ? 'Public' : 'Internal' }}</span>
                        <span>{{ $message->sender_name ?: $message->sender_email }}</span>
                        @if ($message->created_at)
                            <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->timezone(config('app.timezone'))->format('M j, Y g:i a') }}</time>
                        @endif
                    </header>
                    @include('laravel-ticket::desk.entry-body', ['message' => $message, 'ticket' => $ticket])
                </article>
            @endforeach

            @if ($canUpdate)
                <section class="composer">
                    <h2>Public reply</h2>
                    <form class="form-stack" method="post" action="{{ route('laravel-ticket.tickets.reply', $ticket) }}" enctype="multipart/form-data">
                        @csrf
                        @include('laravel-ticket::desk.composer', ['id' => 'reply', 'label' => 'Reply', 'ticket' => $ticket])
                        <button type="submit">Send reply</button>
                    </form>
                </section>
            @endif

            @if ($canNote)
                <section class="composer is-internal">
                    <h2>Internal note</h2>
                    <p class="hint">Agents on this ticket can read it. The requester cannot.</p>
                    <form class="form-stack" method="post" action="{{ route('laravel-ticket.tickets.note', $ticket) }}" enctype="multipart/form-data">
                        @csrf
                        @include('laravel-ticket::desk.composer', ['id' => 'note', 'label' => 'Note', 'ticket' => $ticket])
                        <button class="quiet" type="submit">Save note</button>
                    </form>
                </section>
            @endif
        </div>

        @if ($canUpdate || $canApprove)
            <aside class="dock">
                @if ($canUpdate)
                    <section>
                        <h2>Assign</h2>
                        <form class="form-stack" method="post" action="{{ route('laravel-ticket.tickets.assign', $ticket) }}">
                            @csrf
                            <label for="email">Assignee email
                                <input id="email" name="email" type="email" required>
                            </label>
                            <button type="submit">Assign</button>
                        </form>
                    </section>
                    <section>
                        <h2>Transfer</h2>
                        <form class="form-stack" method="post" action="{{ route('laravel-ticket.tickets.transfer', $ticket) }}">
                            @csrf
                            <label for="department_id">Department
                                <select id="department_id" name="department_id">
                                    <option value="">Keep</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label for="assignment_group_id">Support group
                                <select id="assignment_group_id" name="assignment_group_id">
                                    <option value="">Keep</option>
                                    @foreach ($groups as $group)
                                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="submit">Transfer</button>
                        </form>
                    </section>
                    <section>
                        <h2>Status</h2>
                        <form class="form-stack" method="post" action="{{ route('laravel-ticket.tickets.status', $ticket) }}">
                            @csrf
                            <label for="status">Status
                                <select id="status" name="status">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status->value }}" @if ($ticket->status === $status->value) selected @endif>{{ \Illuminate\Support\Str::of($status->value)->replace('_', ' ')->title() }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="submit">Update status</button>
                        </form>
                    </section>
                @endif

                @if ($canApprove)
                    <section>
                        <h2>Approval</h2>
                        <form method="post" action="{{ route('laravel-ticket.tickets.approve', $ticket) }}">
                            @csrf
                            <div class="row-actions">
                                <button type="submit" name="decision" value="approved">Approve</button>
                                <button class="danger" type="submit" name="decision" value="rejected">Reject</button>
                            </div>
                        </form>
                    </section>
                @endif
            </aside>
        @endif
    </div>
@endsection
