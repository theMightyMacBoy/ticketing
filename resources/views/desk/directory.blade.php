@extends('laravel-ticket::desk.layout')

@section('content')
    <h1>Directory</h1>
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
            @foreach ($profiles as $profile)
                <tr>
                    <td>{{ $profile->name }}</td>
                    <td>{{ $profile->email }}</td>
                    <td>{{ optional($profile->department)->name }}</td>
                    <td>{{ $profile->is_agent ? 'Yes' : 'No' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <form method="post" action="{{ route('laravel-ticket.directory.store') }}">
        @csrf
        <label for="name">Name</label>
        <input id="name" name="name" required>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required>
        <label for="employee_number">Employee number</label>
        <input id="employee_number" name="employee_number">
        <label for="department_id">Department</label>
        <select id="department_id" name="department_id">
            <option value="">None</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}">{{ $department->name }}</option>
            @endforeach
        </select>
        <label><input type="checkbox" name="is_agent" value="1"> Agent</label>
        <label for="authentication">Sign-in</label>
        <select id="authentication" name="authentication">
            <option value="inherit">Detect from the email domain</option>
            <option value="local">Local password</option>
            <option value="sso">Single sign-on</option>
        </select>
        <button type="submit">Save person</button>
    </form>
@endsection
