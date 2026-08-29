@extends('layouts.app')
@section('title', 'Summary '.$cycle->cycle_no)

@section('content')
<div class="page-heading">
    <div>
        <h1>Summary {{ $cycle->cycle_no }}</h1>
        <p>Opening ERP vs hasil scan fisik. Override tidak mengubah hasil scan asli dan seluruh koreksi tercatat sebagai audit admin.</p>
    </div>
    <span class="status large {{ strtolower($cycle->status) }}">{{ $cycle->status }}</span>
</div>

@if($cycle->closing_snapshot_error)
    <div class="alert danger">
        Closing snapshot gagal: {{ $cycle->closing_snapshot_error }}
        <form class="inline-form" method="POST" action="{{ route('admin.cycles.retry-closing', $cycle) }}">
            @csrf
            <button class="btn small">Retry Closing Snapshot</button>
        </form>
    </div>
@endif

@if($errors->has('override'))
    <div class="alert danger">{{ $errors->first('override') }}</div>
@endif

@if($cycle->status !== \App\Models\StockOpnameCycle::STATUS_CLOSED)
    <div class="alert info-lite">
        Manual override baru dapat dilakukan setelah cycle CLOSED agar hasil scan checker sudah final. Export Excel tetap dapat digunakan kapan saja.
    </div>
@endif

<div class="summary-toolbar panel compact-panel">
    <form class="filter-row summary-filter" method="GET">
        <select name="warehouse_id">
            <option value="">Semua Warehouse</option>
            @foreach($cycle->warehouses as $wh)
                <option value="{{ $wh->id }}" @selected($warehouseId === $wh->id)>
                    {{ $wh->warehouse_code }} - {{ $wh->warehouse_name }}
                </option>
            @endforeach
        </select>
        <label class="check">
            <input type="checkbox" name="variance_only" value="1" @checked($varianceOnly)>
            Hanya selisih
        </label>
        <button class="btn" type="submit">Terapkan</button>
    </form>

    <a class="btn excel-btn" href="{{ route('admin.cycles.summary.export', $cycle).(request()->getQueryString() ? '?'.request()->getQueryString() : '') }}">
        Export Excel
    </a>
</div>

<section class="panel">
    <div class="summary-legend">
        <span><strong>Scan Fisik</strong> = hasil scanner asli</span>
        <span><strong>Final Fisik</strong> = Override jika ada, selain itu Scan Fisik</span>
        <span><strong>Variance</strong> = Final Fisik - Opening ERP</span>
        @if($cycle->status === \App\Models\StockOpnameCycle::STATUS_CLOSED)
            <span><strong>Override / Audit</strong> = koreksi admin setelah recount</span>
        @endif
    </div>

    <div class="table-wrap summary-table">
        <table>
            <thead>
                <tr>
                    <th>Warehouse</th>
                    <th>Item</th>
                    <th class="summary-action-col">Override / Audit</th>
                    <th class="num">Opening</th>
                    <th class="num">Scan Fisik</th>
                    <th class="num">Override</th>
                    <th class="num">Final Fisik</th>
                    <th class="num">Variance</th>
                    <th class="num">Closing ERP</th>
                    <th class="num">Net Movement</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php($movementKnown = $row->closing_system_qty !== null)
                    @php($hasMovement = $movementKnown && abs((float) $row->movement_qty) > 0.00005)
                    @php($hasOverride = $row->override_qty !== null)
                    <tr class="{{ abs((float) $row->variance) > 0.00005 ? 'variance-row' : '' }} {{ $hasOverride ? 'override-active' : '' }}">
                        <td>
                            <strong>{{ $row->warehouse_code }}</strong>
                            <small>{{ $row->warehouse_name }}</small>
                        </td>
                        <td>
                            <strong>{{ $row->item_code }}</strong>
                            <small>{{ $row->item_name }}</small>
                        </td>
                        <td class="audit-cell summary-action-col">
                            @if($cycle->status === \App\Models\StockOpnameCycle::STATUS_CLOSED)
                                <details class="override-editor action-override-editor">
                                    <summary class="btn small audit-button">
                                        {{ $hasOverride ? 'Audit / Edit' : 'Override / Audit' }}
                                    </summary>

                                    <div class="override-editor-body">
                                        <div class="audit-links">
                                            @if((int) $row->scan_count > 0)
                                                <a href="{{ route('admin.cycles.scan-detail', [$cycle, $row->warehouse_id, $row->item_id]) }}">Lihat detail scan ({{ number_format($row->scan_count) }})</a>
                                            @else
                                                <span class="muted">Tidak ada scan fisik</span>
                                            @endif
                                        </div>

                                        @if($hasOverride)
                                            <div class="override-audit">
                                                <strong>Override aktif: {{ number_format((float) $row->override_qty, 4, '.', ',') }}</strong>
                                                <small>{{ $row->override_by_name ?: 'Admin' }} · {{ $row->override_updated_at ? \Carbon\Carbon::parse($row->override_updated_at)->format('d/m/Y H:i:s') : '-' }}</small>
                                                <p>{{ $row->override_comment }}</p>
                                            </div>
                                        @endif

                                        <form method="POST" action="{{ route('admin.cycles.summary.override', [$cycle, $row->warehouse_id, $row->item_id]) }}">
                                            @csrf
                                            @method('PUT')
                                            <label>
                                                Qty Override
                                                <input type="number" name="override_qty" min="0" step="0.0001" value="{{ $hasOverride ? (float) $row->override_qty : '' }}" required>
                                                <small>Isi dalam smallest UOM yang sama dengan Opening.</small>
                                            </label>
                                            <label>
                                                Comment
                                                <textarea name="comment" rows="3" maxlength="2000" required placeholder="Contoh: recount menemukan 5 PCS di rak belakang">{{ $hasOverride ? $row->override_comment : '' }}</textarea>
                                            </label>
                                            <button class="btn primary small" type="submit">Simpan Override</button>
                                        </form>

                                        @if($hasOverride)
                                            <form method="POST" action="{{ route('admin.cycles.summary.override.destroy', [$cycle, $row->warehouse_id, $row->item_id]) }}" onsubmit="return confirm('Hapus override? Final fisik akan kembali memakai hasil scan asli.');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="danger-link" type="submit">Hapus Override</button>
                                            </form>
                                        @endif
                                    </div>
                                </details>
                            @else
                                <div class="audit-links">
                                    @if((int) $row->scan_count > 0)
                                        <a href="{{ route('admin.cycles.scan-detail', [$cycle, $row->warehouse_id, $row->item_id]) }}">Detail scan</a>
                                    @else
                                        <span class="muted">Belum CLOSED</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="num">{{ number_format((float) $row->opening_system_qty, 4, '.', ',') }}</td>
                        <td class="num">
                            {{ number_format((float) $row->physical_qty, 4, '.', ',') }}
                            <small>{{ number_format($row->scan_count) }} scan</small>
                        </td>
                        <td class="num">
                            @if($hasOverride)
                                <strong class="override-value">{{ number_format((float) $row->override_qty, 4, '.', ',') }}</strong>
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                        <td class="num">
                            <strong>{{ number_format((float) $row->final_physical_qty, 4, '.', ',') }}</strong>
                            @if($hasOverride)<small class="override-label">OVERRIDE</small>@endif
                        </td>
                        <td class="num {{ (float) $row->variance < 0 ? 'negative' : ((float) $row->variance > 0 ? 'positive' : '') }}">
                            <strong>{{ number_format((float) $row->variance, 4, '.', ',') }}</strong>
                        </td>
                        <td class="num">{{ $movementKnown ? number_format((float) $row->closing_system_qty, 4, '.', ',') : '-' }}</td>
                        <td class="num">
                            @if(!$movementKnown)
                                <span class="muted">Belum ada</span>
                            @elseif($hasMovement)
                                <span class="movement">⚠ {{ number_format((float) $row->movement_qty, 4, '.', ',') }}</span>
                            @else
                                <span class="ok-text">0</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="empty">Belum ada data summary.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $rows->links() }}
</section>
@endsection
