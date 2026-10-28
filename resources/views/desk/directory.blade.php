@extends('laravel-ticket::desk.layout')

@section('title', 'Directory')

@section('content')
    <div class="page-head">
        <div>
            <p class="kicker">Administration</p>
            <h1>Directory</h1>
            <p class="lede">People who can sign in, and who works as an agent. Agent rights come from this list.</p>
        </div>
    </div>
    <div class="split-section">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Agent</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($profiles as $profile)
                        <tr>
                            <td>{{ $profile->name }}</td>
                            <td class="mono">{{ $profile->email }}</td>
                            <td>{{ optional($profile->department)->name }}</td>
                            <td>{{ $profile->is_agent ? 'Agent' : 'Requester' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No people in the directory.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <form class="form-stack" method="post" action="{{ route('laravel-ticket.directory.store') }}">
            @csrf
            <h2>Add a person</h2>
            <label for="name">Name
                <input id="name" name="name" required>
            </label>
            <label for="email">Email
                <input id="email" name="email" type="email" required>
            </label>
            <label for="employee_number">Employee number
                <input id="employee_number" name="employee_number">
            </label>
            <label for="department_id">Department
                <select id="department_id" name="department_id">
                    <option value="">None</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="check">
                <input type="checkbox" name="is_agent" value="1">
                <span>Agent</span>
            </label>
            <label for="authentication">Sign-in
                <select id="authentication" name="authentication">
                    <option value="inherit">Detect from the email domain</option>
                    <option value="local">Local password</option>
                    <option value="sso">Single sign-on</option>
                </select>
            </label>
            <button type="submit">Save person</button>
        </form>
    </div>
@endsection
