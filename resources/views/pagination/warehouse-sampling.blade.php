{{-- Compact native Laravel pagination for Sampling Gudang and its History only.
     Receives $paginator from $periods->links() / $rows->links().
     Pagination URLs (including any active filters) come from Laravel. --}}
@if ($paginator->hasPages())
    @once
        @push('styles')
            <style>
                .ws-pager { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; max-width:100%; padding:8px 0; font-family:inherit; font-size:12px; line-height:1.4; }
                .ws-pager__info { color:#667085; }
                .ws-pager__links { display:flex; flex-wrap:wrap; align-items:center; justify-content:flex-end; gap:5px; }
                .ws-pager__link { box-sizing:border-box; display:inline-flex; align-items:center; justify-content:center; min-width:33px; min-height:33px; padding:6px 9px; border:1px solid #d0d5dd; border-radius:8px; background:#fff; color:#344054; font:inherit; font-weight:650; text-decoration:none; white-space:nowrap; }
                a.ws-pager__link:hover, a.ws-pager__link:focus-visible { color:#175cd3; border-color:#84adff; background:#eff6ff; }
                .ws-pager__link--active { background:#175cd3; color:#fff; border-color:#175cd3; }
                .ws-pager__link--disabled { background:#f9fafb; color:#98a2b3; cursor:not-allowed; }
                .ws-pager__dots { padding:0 3px; color:#98a2b3; }
                @media(max-width:560px) { .ws-pager { align-items:stretch; flex-direction:column; } .ws-pager__links { justify-content:flex-start; } .ws-pager__link { min-width:31px; min-height:32px; padding:5px 7px; } }
            </style>
        @endpush
    @endonce
    @php
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $startPage = max(1, $currentPage - 2);
        $endPage = min($lastPage, $currentPage + 2);
    @endphp
    <nav class="ws-pager" role="navigation" aria-label="Navigasi halaman">
        <span class="ws-pager__info">
            Menampilkan {{ number_format($paginator->firstItem() ?? 0) }}–{{ number_format($paginator->lastItem() ?? 0) }}
            dari {{ number_format($paginator->total()) }} data
        </span>
        <div class="ws-pager__links">
            @if ($paginator->onFirstPage())
                <span class="ws-pager__link ws-pager__link--disabled" aria-disabled="true">← Sebelumnya</span>
            @else
                <a class="ws-pager__link" href="{{ $paginator->previousPageUrl() }}" rel="prev">← Sebelumnya</a>
            @endif

            @if ($startPage > 1)
                <a class="ws-pager__link" href="{{ $paginator->url(1) }}" aria-label="Halaman 1">1</a>
                @if ($startPage > 2)
                    <span class="ws-pager__dots" aria-hidden="true">…</span>
                @endif
            @endif

            @for ($page = $startPage; $page <= $endPage; $page++)
                @if ($page === $currentPage)
                    <span class="ws-pager__link ws-pager__link--active" aria-current="page">{{ $page }}</span>
                @else
                    <a class="ws-pager__link" href="{{ $paginator->url($page) }}" aria-label="Halaman {{ $page }}">{{ $page }}</a>
                @endif
            @endfor

            @if ($endPage < $lastPage)
                @if ($endPage < $lastPage - 1)
                    <span class="ws-pager__dots" aria-hidden="true">…</span>
                @endif
                <a class="ws-pager__link" href="{{ $paginator->url($lastPage) }}" aria-label="Halaman {{ $lastPage }}">{{ $lastPage }}</a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="ws-pager__link" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya →</a>
            @else
                <span class="ws-pager__link ws-pager__link--disabled" aria-disabled="true">Berikutnya →</span>
            @endif
        </div>
    </nav>
@endif
