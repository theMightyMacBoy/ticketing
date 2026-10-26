@extends('laravel-ticket::desk.layout')

@section('content')
    <h1>Setup</h1>

    <section>
        <h2>Departments</h2>
        <ul>
            @foreach ($departments as $department)
                <li>{{ $department->name }}</li>
            @endforeach
        </ul>
        <form method="post" action="{{ route('laravel-ticket.setup.departments') }}">
            @csrf
            <label for="department-name">Name</label>
            <input id="department-name" name="name" required>
            <button type="submit">Add department</button>
        </form>
    </section>

    <section>
        <h2>Support groups</h2>
        <ul>
            @foreach ($groups as $group)
                <li>{{ $group->name }}</li>
            @endforeach
        </ul>
        <form method="post" action="{{ route('laravel-ticket.setup.groups') }}">
            @csrf
            <label for="group-name">Name</label>
            <input id="group-name" name="name" required>
            <label for="group-department">Department</label>
            <select id="group-department" name="department_id" required>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                @endforeach
            </select>
            <button type="submit">Add group</button>
        </form>
    </section>

    <section>
        <h2>Ticket type</h2>
        <form method="post" action="{{ route('laravel-ticket.setup.types') }}">
            @csrf
            <label for="type-name">Name</label>
            <input id="type-name" name="name" required>
            <label for="type-prefix">Prefix</label>
            <input id="type-prefix" name="prefix" required maxlength="12">
            <label for="type-department">Department</label>
            <select id="type-department" name="department_id">
                <option value="">Global</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                @endforeach
            </select>
            <label><input type="checkbox" name="requires_approval" value="1"> Requires approval</label>
            <button type="submit">Add type</button>
        </form>
    </section>

    <section>
        <h2>Catalog item</h2>
        <form method="post" action="{{ route('laravel-ticket.setup.services') }}">
            @csrf
            <label for="service-name">Name</label>
            <input id="service-name" name="name" required>
            <label for="service-department">Department</label>
            <select id="service-department" name="department_id" required>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                @endforeach
            </select>
            <label for="service-group">Support group</label>
            <select id="service-group" name="assignment_group_id">
                <option value="">None</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                @endforeach
            </select>
            <label for="field-key">Required field key</label>
            <input id="field-key" name="field_key">
            <label for="field-name">Required field label</label>
            <input id="field-name" name="field_name">
            <button type="submit">Add catalog item</button>
        </form>
    </section>

    <section>
        <h2>Calendar</h2>
        <ul>
            @foreach ($calendars as $calendar)
                <li>{{ $calendar->name }} · {{ $calendar->timezone }}</li>
            @endforeach
        </ul>
        <form method="post" action="{{ route('laravel-ticket.setup.calendars') }}">
            @csrf
            <label for="calendar-department">Department</label>
            <select id="calendar-department" name="department_id" required>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                @endforeach
            </select>
            <label for="timezone">Timezone</label>
            <input id="timezone" name="timezone" value="America/Detroit" required>
            <button type="submit">Save weekday hours</button>
        </form>
    </section>

    <section>
        <h2>Mailboxes</h2>
        <ul>
            @foreach ($mailboxes as $mailbox)
                <li>{{ $mailbox->address }} @if (! $mailbox->is_active) inactive @endif
                    @if ($mailbox->last_error) · {{ $mailbox->last_error }} @endif
                </li>
            @endforeach
        </ul>
        <form method="post" action="{{ route('laravel-ticket.setup.mailboxes') }}">
            @csrf
            <label for="mailbox-name">Name</label>
            <input id="mailbox-name" name="name" required>
            <label for="mailbox-address">Address</label>
            <input id="mailbox-address" name="address" type="email" required>
            <label for="mailbox-department">Department</label>
            <select id="mailbox-department" name="department_id">
                <option value="">None</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                @endforeach
            </select>
            <label for="retention">Delete read mail after days</label>
            <input id="retention" name="delete_read_after_days" type="number" min="1">
            <button type="submit">Add inactive mailbox</button>
        </form>
    </section>
@endsection
