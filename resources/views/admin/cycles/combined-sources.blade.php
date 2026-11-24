@php($combinedSources = app(\App\Services\CombinedCycleSnapshotService::class)->sources($cycle))
<section class="panel" style="margin-bottom:16px;padding:16px">
    <h2>Cycle Gabungan — {{ $combinedSources->count() }} Gudang</h2>
    <p>Area COMBINED hanya untuk menghitung fisik. Stok ERP dan alokasi tetap dilacak per database/gudang.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0">
        @foreach($combinedSources as $source)
            <span style="border:1px solid #cbd5e1;border-radius:7px;padding:6px 10px">{{ $source->source_database }} · {{ $source->warehouse_code }} — {{ $source->warehouse_name }}</span>
        @endforeach
    </div>
    @if($cycle->status !== 'DRAFT')
        <a class="btn primary" href="{{ route('admin.cycles.combined-allocation',$cycle) }}">Lihat Proporsi per Gudang</a>
        <a class="btn" href="{{ route('admin.cycles.combined-allocation.export',$cycle) }}">Export Excel Proporsi</a>
    @endif
</section>
