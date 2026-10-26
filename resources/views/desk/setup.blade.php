@extends('laravel-ticket::desk.layout')

@section('title', 'Setup')

@section('content')
    <div class="page-head">
        <div>
            <p class="kicker">Administration</p>
            <h1>Setup</h1>
            <p class="lede">Departments, groups, mail, and sign-in. New mailboxes are saved inactive.</p>
        </div>
    </div>
    <nav class="jump" aria-label="Setup sections">
        <a href="#departments">Departments</a>
        <a href="#groups">Support groups</a>
        <a href="#types">Ticket type</a>
        <a href="#catalog">Catalog item</a>
        <a href="#calendars">Calendar</a>
        <a href="#mailboxes">Mailboxes</a>
        <a href="#sign-in">Single sign-on</a>
    </nav>

    <section class="setup-section" id="departments">
        <div>
            <h2>Departments</h2>
            <ul class="plain">
                @forelse ($departments as $department)
                    <li>{{ $department->name }}</li>
                @empty
                    <li class="muted">None yet.</li>
                @endforelse
            </ul>
        </div>
        <form class="form-stack" method="post" action="{{ route('laravel-ticket.setup.departments') }}">
            @csrf
            <label for="department-name">Name
                <input id="department-name" name="name" required>
            </label>
            <button type="submit">Add department</button>
        </form>
    </section>

    <section class="setup-section" id="groups">
        <div>
            <h2>Support groups</h2>
            <ul class="plain">
                @forelse ($groups as $group)
                    <li>
                        {{ $group->name }}
                        @if (optional($group->department)->name)
                            <span class="muted">{{ $group->department->name }}</span>
                        @endif
                        <form class="group-block" method="post" action="{{ route('laravel-ticket.setup.groups.managers', $group) }}">
                            @csrf
                            <label class="check" for="block-{{ $group->id }}">
                                <input id="block-{{ $group->id }}" type="checkbox" name="hide_from_managers" value="1" @checked($group->hide_from_managers)>
                                <span>Block the requester's manager</span>
                            </label>
                            <button class="quiet" type="submit">Save</button>
                        </form>
                    </li>
                @empty
                    <li class="muted">None yet.</li>
                @endforelse
            </ul>
        </div>
        <form class="form-stack" method="post" action="{{ route('laravel-ticket.setup.groups') }}">
            @csrf
            <label for="group-name">Name
                <input id="group-name" name="name" required>
            </label>
            <label for="group-department">Department
                <select id="group-department" name="department_id" required>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="check">
                <input type="checkbox" name="hide_from_managers" value="1">
                <span>Block the requester's manager</span>
            </label>
            <p class="hint">Tickets on this group stay out of the team queues. Use it for a queue such as HR, where the employee's manager should not read the case.</p>
            <button type="submit">Add group</button>
        </form>
    </section>

    <section class="setup-section" id="types">
        <div>
            <h2>Ticket type</h2>
            <ul class="plain">
                @forelse ($types as $type)
                    <li>
                        {{ $type->name }}
                        <span class="muted">{{ $type->prefix }}@if ($type->requires_approval) · Requires approval @endif @if (optional($type->department)->name) · {{ $type->department->name }} @else · Global @endif</span>
                    </li>
                @empty
                    <li class="muted">None yet.</li>
                @endforelse
            </ul>
        </div>
        <form class="form-stack" method="post" action="{{ route('laravel-ticket.setup.types') }}">
            @csrf
            <label for="type-name">Name
                <input id="type-name" name="name" required>
            </label>
            <label for="type-prefix">Prefix
                <input id="type-prefix" name="prefix" required maxlength="12">
            </label>
            <label for="type-department">Department
                <select id="type-department" name="department_id">
                    <option value="">Global</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="check">
                <input type="checkbox" name="requires_approval" value="1">
                <span>Requires approval</span>
            </label>
            <button type="submit">Add type</button>
        </form>
    </section>

    <section class="setup-section" id="catalog">
        <div>
            <h2>Catalog item</h2>
            <ul class="plain">
                @forelse ($services as $item)
                    <li>{{ $item->name }}</li>
                @empty
                    <li class="muted">None yet.</li>
                @endforelse
            </ul>
        </div>
        <form class="form-stack" method="post" action="{{ route('laravel-ticket.setup.services') }}">
            @csrf
            <label for="service-name">Name
                <input id="service-name" name="name" required>
            </label>
            <label for="service-department">Department
                <select id="service-department" name="department_id" required>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
            <label for="service-group">Support group
                <select id="service-group" name="assignment_group_id">
                    <option value="">None</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>
            </label>
            <label for="field-key">Required field key
                <input id="field-key" name="field_key">
            </label>
            <label for="field-name">Required field label
                <input id="field-name" name="field_name">
            </label>
            <button type="submit">Add catalog item</button>
        </form>
    </section>

    <section class="setup-section" id="calendars">
        <div>
            <h2>Calendar</h2>
            <ul class="plain">
                @forelse ($calendars as $calendar)
                    <li>{{ $calendar->name }} <span class="muted">{{ $calendar->timezone }}</span></li>
                @empty
                    <li class="muted">None yet.</li>
                @endforelse
            </ul>
        </div>
        <form class="form-stack" method="post" action="{{ route('laravel-ticket.setup.calendars') }}">
            @csrf
            <label for="calendar-department">Department
                <select id="calendar-department" name="department_id" required>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
            <label for="timezone">Timezone
                <input id="timezone" name="timezone" value="America/Detroit" required>
            </label>
            <button type="submit">Save weekday hours</button>
        </form>
    </section>

    <section class="setup-section" id="mailboxes">
        <div>
            <h2>Mailboxes</h2>
            <ul class="plain">
                @forelse ($mailboxes as $mailbox)
                    <li>
                        <span class="mono">{{ $mailbox->address }}</span>
                        @if (! $mailbox->is_active)
                            <span class="muted">Inactive</span>
                        @endif
                        @if ($mailbox->last_error)
                            <span class="error-inline">{{ $mailbox->last_error }}</span>
                        @endif
                    </li>
                @empty
                    <li class="muted">None yet.</li>
                @endforelse
            </ul>
        </div>
        <form class="form-stack" method="post" action="{{ route('laravel-ticket.setup.mailboxes') }}">
            @csrf
            <label for="mailbox-name">Name
                <input id="mailbox-name" name="name" required>
            </label>
            <label for="mailbox-address">Address
                <input id="mailbox-address" name="address" type="email" required>
            </label>
            <label for="mailbox-department">Department
                <select id="mailbox-department" name="department_id">
                    <option value="">None</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
            <label for="retention">Delete read mail after days
                <input id="retention" name="delete_read_after_days" type="number" min="1">
            </label>
            <button type="submit">Add inactive mailbox</button>
        </form>
    </section>

    <section class="setup-section" id="sign-in">
        <div>
            <h2>Single sign-on</h2>
            <ul class="plain">
                @forelse ($providers as $provider)
                    <li>
                        {{ $provider->name }}
                        <span class="muted">{{ implode(', ', $provider->domains) }}@if ($provider->sync_manager) · Manager sync @endif</span>
                        <span class="issuer muted">{{ $provider->issuer }}</span>
                    </li>
                @empty
                    <li class="muted">None yet.</li>
                @endforelse
            </ul>
        </div>
        <form class="form-stack" method="post" action="{{ route('laravel-ticket.setup.providers') }}">
            @csrf
            <label for="provider-name">Name
                <input id="provider-name" name="name" required>
            </label>
            <label for="provider-domain">Email domain
                <input id="provider-domain" name="domain" required placeholder="contoso.com">
            </label>
            <label for="provider-issuer">Issuer
                <input id="provider-issuer" name="issuer" required placeholder="https://login.microsoftonline.com/tenant-id/v2.0">
            </label>
            <label for="provider-client-id">Client id
                <input id="provider-client-id" name="client_id" required>
            </label>
            <label for="provider-client-secret">Client secret
                <input id="provider-client-secret" name="client_secret" type="password" required autocomplete="new-password">
            </label>
            <label class="check">
                <input type="checkbox" name="sync_manager" value="1" checked>
                <span>Sync each person's manager from Microsoft Graph</span>
            </label>
            <p class="hint">The sign-in app needs application permission User.Read.All. An ID token that already names the manager is stored either way. Sign-in still completes if Graph refuses the lookup.</p>
            <button type="submit">Add sign-in provider</button>
        </form>
    </section>
@endsection
