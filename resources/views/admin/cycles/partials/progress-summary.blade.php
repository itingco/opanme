@if(($cycleProgress['total_sku'] ?? 0) > 0)
<section class="panel compact-panel" aria-label="Rangkuman progress cycle">
    <div class="panel-head">
        <div>
            <h2>Rangkuman Opname</h2>
            <small>Total SKU dibandingkan dengan hasil fisik terakhir{{ request('warehouse_id') ? ' untuk warehouse terpilih' : '' }}.</small>
        </div>
    </div>
    <div class="metric-grid">
        <article class="metric"><span>Total SKU</span><strong>{{ number_format($cycleProgress['total_sku']) }}</strong></article>
        <article class="metric"><span>Sudah Cocok</span><strong>{{ number_format($cycleProgress['matched_sku']) }}</strong><small>Variance = 0</small></article>
        <article class="metric"><span>Masih Selisih</span><strong>{{ number_format($cycleProgress['variance_sku']) }}</strong><small>Perlu review / override</small></article>
    </div>
</section>
@endif
