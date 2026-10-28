@if ($tickets->previousPageUrl() || $tickets->nextPageUrl())
    <nav class="pager" aria-label="Pages">
        @if ($tickets->previousPageUrl())
            <a href="{{ $tickets->previousPageUrl() }}">Previous</a>
        @endif
        @if ($tickets->nextPageUrl())
            <a href="{{ $tickets->nextPageUrl() }}">Next</a>
        @endif
    </nav>
@endif
