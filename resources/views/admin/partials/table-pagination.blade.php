@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $from = max(1, $current - 2);
    $to = min($last, $current + 2);
@endphp

<div class="data-table-footer">
    <div class="data-table-count">
        @if($paginator->total() > 0)
            Menampilkan <strong>{{ number_format($paginator->firstItem()) }}</strong>–<strong>{{ number_format($paginator->lastItem()) }}</strong>
            dari <strong>{{ number_format($paginator->total()) }}</strong> data
        @else
            Tidak ada data
        @endif
    </div>

    @if($paginator->hasPages())
        <nav class="table-pager" aria-label="Pagination">
            @if($paginator->onFirstPage())
                <span class="pager-btn disabled" aria-disabled="true">‹</span>
            @else
                <a class="pager-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya">‹</a>
            @endif

            @if($from > 1)
                <a class="pager-btn" href="{{ $paginator->url(1) }}">1</a>
                @if($from > 2)<span class="pager-dots">…</span>@endif
            @endif

            @for($page = $from; $page <= $to; $page++)
                @if($page === $current)
                    <span class="pager-btn active" aria-current="page">{{ $page }}</span>
                @else
                    <a class="pager-btn" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                @endif
            @endfor

            @if($to < $last)
                @if($to < $last - 1)<span class="pager-dots">…</span>@endif
                <a class="pager-btn" href="{{ $paginator->url($last) }}">{{ $last }}</a>
            @endif

            @if($paginator->hasMorePages())
                <a class="pager-btn" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya">›</a>
            @else
                <span class="pager-btn disabled" aria-disabled="true">›</span>
            @endif
        </nav>
    @endif
</div>
