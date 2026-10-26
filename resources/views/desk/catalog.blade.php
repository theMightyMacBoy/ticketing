@extends('laravel-ticket::desk.layout')

@section('content')
    <h1>Catalog</h1>
    <ul>
        @foreach ($services as $item)
            <li><a href="{{ route('laravel-ticket.catalog', ['service' => $item->id]) }}">{{ $item->name }}</a></li>
        @endforeach
    </ul>

    @if ($service)
        <h2>{{ $service->name }}</h2>
        <form method="post" action="{{ route('laravel-ticket.catalog.store') }}">
            @csrf
            <input type="hidden" name="service_id" value="{{ $service->id }}">
            <label for="title">Title</label>
            <input id="title" name="title" required>
            <label for="message">Details</label>
            <textarea id="message" name="message"></textarea>
            @foreach ($fields as $field)
                <label for="field-{{ $field->key }}">{{ $field->name }}</label>
                <input id="field-{{ $field->key }}" name="fields[{{ $field->key }}]" @if ($field->is_required) required @endif>
            @endforeach
            <label>
                <input type="checkbox" name="is_confidential" value="1"> Confidential
            </label>
            <button type="submit">Open ticket</button>
        </form>
    @endif
@endsection
