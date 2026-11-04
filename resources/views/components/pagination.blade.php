@props(['paginator', 'more' => 'View more', 'previous' => 'Previous'])

{{-- Centred "View more" button, with a way back once past the first page. --}}
@if ($paginator->hasPages())
    <nav aria-label="Pagination" class="mt-10 flex flex-wrap items-center justify-center gap-3">
        @unless ($paginator->onFirstPage())
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-outline btn-sm">
                <x-ocean-icon name="arrow-left" class="size-4" /> {{ $previous }}
            </a>
        @endunless
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-primary">{{ $more }}</a>
        @endif
        <p class="w-full text-center text-xs text-slate-muted">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</p>
    </nav>
@endif
