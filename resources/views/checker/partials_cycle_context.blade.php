<div style="display:grid;gap:5px;padding:10px 12px;margin:10px 0;background:#f1f5f9;border:1px solid #dbe4ee;border-radius:9px;font-size:12px;line-height:1.5;color:#334155">
    <div>Database: <strong>{{ $cycle->source_database }}</strong></div>
    @if($cycle->source_database === 'COMBINED')
        @php($sources = \Illuminate\Support\Facades\DB::table('cycle_combined_sources')->where('cycle_id', $cycle->id)->orderBy('id')->get(['source_database','warehouse_code']))
        <div>Gudang sumber: <strong>{{ $sources->map(fn($source) => $source->source_database.' / '.$source->warehouse_code)->implode(', ') ?: '-' }}</strong></div>
    @endif
    <div>Tanggal opname (cut-off): <strong>{{ $cycle->cutoff_date?->format('d-m-Y') ?? '-' }}</strong></div>
    <div>Cycle dibuka: <strong>{{ $cycle->started_at?->format('d-m-Y H:i') ?? '-' }}</strong></div>
</div>
