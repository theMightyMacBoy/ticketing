@extends('laravel-ticket::desk.layout')

@section('title', 'Catalog')

@section('content')
    <div class="page-head">
        <div>
            <p class="kicker">Catalog</p>
            <h1>Open a request</h1>
            <p class="lede">Choose a service. Required fields are marked by the browser before the ticket is filed.</p>
        </div>
    </div>

    <div class="catalog-layout">
        <ul class="service-list">
            @forelse ($services as $item)
                <li>
                    <a href="{{ route('laravel-ticket.catalog', ['service' => $item->id]) }}" @if ($service && $service->id === $item->id) class="is-current" aria-current="page" @endif>
                        <strong>{{ $item->name }}</strong>
                        @if ($item->description)
                            <span>{{ $item->description }}</span>
                        @endif
                    </a>
                </li>
            @empty
                <li class="muted">No catalog items yet.</li>
            @endforelse
        </ul>

        @if ($service)
            <form class="form-stack" method="post" action="{{ route('laravel-ticket.catalog.store') }}">
                @csrf
                <h2>{{ $service->name }}</h2>
                <input type="hidden" name="service_id" value="{{ $service->id }}">
                <label for="title">Title
                    <input id="title" name="title" value="{{ old('title') }}" required>
                </label>
                <label for="message">Details
                    <textarea id="message" name="message">{{ old('message') }}</textarea>
                </label>
                @foreach ($fields as $field)
                    <label for="field-{{ $field->key }}">{{ $field->name }}
                        @if ($field->data_type === 'select' && is_array($field->options) && $field->options !== [])
                            <select id="field-{{ $field->key }}" name="fields[{{ $field->key }}]" @if ($field->is_required) required @endif>
                                <option value="">Choose</option>
                                @foreach ($field->options as $option)
                                    <option value="{{ $option }}" @if (old('fields.'.$field->key) == $option) selected @endif>{{ $option }}</option>
                                @endforeach
                            </select>
                        @else
                            <input
                                id="field-{{ $field->key }}"
                                name="fields[{{ $field->key }}]"
                                type="{{ $field->data_type === 'number' ? 'number' : ($field->data_type === 'date' ? 'date' : 'text') }}"
                                value="{{ old('fields.'.$field->key) }}"
                                @if ($field->is_required) required @endif
                            >
                        @endif
                    </label>
                @endforeach
                <label class="check">
                    <input type="checkbox" name="is_confidential" value="1" @if (old('is_confidential')) checked @endif>
                    <span>Confidential</span>
                </label>
                <button type="submit">Open ticket</button>
            </form>
        @else
            <p class="lede">Select a catalog item to file a ticket.</p>
        @endif
    </div>
@endsection
