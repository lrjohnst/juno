@props(['paginator'])
@if ($paginator->hasPages())
    <div class="pagination">
        @if ($paginator->onFirstPage()) <span class="muted">‹ Vorige</span> @else <a href="{{ $paginator->previousPageUrl() }}">‹ Vorige</a> @endif
        <span class="current">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages()) <a href="{{ $paginator->nextPageUrl() }}">Volgende ›</a> @else <span class="muted">Volgende ›</span> @endif
    </div>
@endif
