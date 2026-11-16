@extends('layouts.app')
@section('title', 'Summary '.$cycle->cycle_no)

@section('content')
<div class="page-heading">
    <div>
        <h1>Summary {{ $cycle->cycle_no }}</h1>
        <p>Opening ERP vs hasil fisik. Barang yang tidak ada di ERP tetap dapat dicatat sebagai <strong>Non-System</strong> tanpa membuat master baru di ERP.</p>
    </div>
    <span class="status large {{ strtolower($cycle->status) }}">{{ $cycle->status }}</span>
</div>

@if($cycle->closing_snapshot_error && $cycle->status === \App\Models\StockOpnameCycle::STATUS_CLOSED)
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
@if($errors->has('non_system'))
    <div class="alert danger">{{ $errors->first('non_system') }}</div>
@endif
@if($errors->has('finalize'))
    <div class="alert danger">{{ $errors->first('finalize') }}</div>
@endif
@if($errors->has('report'))
    <div class="alert danger">{{ $errors->first('report') }}</div>
@endif
@if($errors->any() && !$errors->has('override') && !$errors->has('non_system') && !$errors->has('finalize') && !$errors->has('report'))
    <div class="alert danger">{{ $errors->first() }}</div>
@endif

@if(in_array($cycle->status, [\App\Models\StockOpnameCycle::STATUS_DRAFT, \App\Models\StockOpnameCycle::STATUS_OPEN], true))
    <div class="alert info-lite">
        Manual override baru dapat dilakukan setelah cycle CLOSED. Saat OPEN, checker tetap dapat mencatat barang Non-System dari halaman scan.
    </div>
@endif

@if($cycle->status === \App\Models\StockOpnameCycle::STATUS_CLOSED)
    @if(!$cycle->closing_snapshot_error && $cycle->closing_snapshot_at)
        <section class="panel finalization-panel">
            <div class="finalization-copy">
                <span class="finalization-icon">🔒</span>
                <div>
                    <h2>Siap masuk tahap FINAL</h2>
                    <p>Periksa seluruh selisih, barang Non-System, dan override terlebih dahulu. Setelah difinalisasi seluruh koreksi terkunci permanen.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.cycles.finalize', $cycle) }}" onsubmit="return confirm('FINALISASI PERMANEN? Setelah proses ini seluruh hasil, termasuk barang Non-System, akan dikunci.');">
                @csrf
                <input type="hidden" name="confirm_finalization" value="1">
                <button class="btn finalize-btn" type="submit">🔒 Finalisasi Stock Opname</button>
            </form>
        </section>
    @else
        <div class="alert warning-lite"><strong>Finalisasi belum dapat dilakukan.</strong> Closing Snapshot harus berhasil terlebih dahulu.</div>
    @endif
@endif

@if($cycle->status === \App\Models\StockOpnameCycle::STATUS_FINALIZED)
    <section class="panel finalized-banner">
        <div class="finalization-copy">
            <span class="finalization-icon">✓</span>
            <div>
                <h2>Hasil sudah FINAL dan terkunci</h2>
                <p>Difinalisasi {{ optional($cycle->finalized_at)->format('d/m/Y H:i:s') ?: '-' }} oleh <strong>{{ optional($cycle->finalizer)->name ?: 'Admin' }}</strong>. Seluruh data hanya dapat dibaca.</p>
            </div>
        </div>
        <a class="btn primary" href="{{ route('admin.cycles.final-report', $cycle) }}">📄 Download Laporan Final PDF</a>
    </section>
@endif

<div class="summary-toolbar panel compact-panel">
    <form class="filter-row summary-filter" method="GET">
        <select name="warehouse_id">
            <option value="">Semua Warehouse</option>
            @foreach($cycle->warehouses as $wh)
                <option value="{{ $wh->id }}" @selected($warehouseId === $wh->id)>{{ $wh->warehouse_code }} - {{ $wh->warehouse_name }}</option>
            @endforeach
        </select>
        <label class="check"><input type="checkbox" name="variance_only" value="1" @checked($varianceOnly)> Hanya selisih</label>
        <button class="btn" type="submit">Terapkan</button>
    </form>

    <div class="summary-export-actions">
        @if($cycle->status === \App\Models\StockOpnameCycle::STATUS_CLOSED)
            <details class="non-system-admin-add">
                <summary class="btn non-system-add-btn">+ Barang Non-System</summary>
                <div class="non-system-admin-form-wrap">
                    <div class="non-system-panel">
                        <strong>Barang ada di fisik, tidak ada di ERP</strong>
                        <small>Entry ini hanya masuk cycle stock opname dan tidak membuat master ERP.</small>
                    </div>
                    <form method="POST" action="{{ route('admin.cycles.summary.non-system.store', $cycle) }}" class="non-system-admin-form">
                        @csrf
                        <label>Warehouse
                            <select name="warehouse_id" required>
                                <option value="">Pilih Warehouse</option>
                                @foreach($cycle->warehouses as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->warehouse_code }} - {{ $wh->warehouse_name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Barcode / Kode Temuan<input name="barcode" maxlength="150" required placeholder="Scan atau ketik barcode/kode"></label>
                        <label>Nama Barang<input name="item_name" maxlength="255" required placeholder="Nama barang fisik"></label>
                        <div class="grid-2">
                            <label>Qty Override<input type="number" name="qty" min="0" step="0.0001" value="1" required></label>
                            <label>UOM<input name="uom_code" maxlength="50" value="PCS" required></label>
                            <label>Isi per UOM<input type="number" name="ratio_to_smallest" min="0.0001" step="0.0001" value="1" required></label>
                            <label>Smallest UOM<input name="smallest_uom_code" maxlength="50" value="PCS" required></label>
                        </div>
                        <small class="muted">Contoh: 2 KRTN × 20 = 40 PCS. Jika UOM sama dengan Smallest UOM, ratio otomatis dianggap 1.</small>
                        <label>Comment<textarea name="comment" rows="3" maxlength="2000" required placeholder="Contoh: ditemukan saat recount di rak belakang"></textarea></label>
                        <button class="btn primary" type="submit">Simpan Barang Non-System</button>
                    </form>
                </div>
            </details>
        @endif
        <a class="btn excel-btn" href="{{ route('admin.cycles.summary.export', $cycle).(request()->getQueryString() ? '?'.request()->getQueryString() : '') }}">Export Excel</a>
        @if($cycle->status === \App\Models\StockOpnameCycle::STATUS_FINALIZED)
            <a class="btn primary" href="{{ route('admin.cycles.final-report', $cycle) }}">Laporan Final PDF</a>
        @endif
    </div>
</div>

<section class="panel">
    <div class="summary-legend">
        <span><strong>ERP</strong> = item berasal dari snapshot/master ERP</span>
        <span><strong>NON-SYSTEM</strong> = ada di fisik tetapi tidak ditemukan di ERP</span>
        <span><strong>Final Fisik</strong> = Override jika ada, selain itu Scan Fisik</span>
        <span><strong>Variance</strong> = Final Fisik - Opening ERP</span>
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
                    @php($isNonSystem = $row->row_type === 'NON_SYSTEM')
                    @php($movementKnown = $row->closing_system_qty !== null)
                    @php($hasMovement = $movementKnown && abs((float) $row->movement_qty) > 0.00005)
                    @php($hasOverride = $row->override_qty !== null)
                    <tr class="{{ abs((float) $row->variance) > 0.00005 ? 'variance-row' : '' }} {{ $hasOverride ? 'override-active' : '' }} {{ $isNonSystem ? 'non-system-summary-row' : '' }}">
                        <td><strong>{{ $row->warehouse_code }}</strong><small>{{ $row->warehouse_name }}</small></td>
                        <td>
                            <strong>{{ $row->item_code }}</strong>
                            @if($isNonSystem)<span class="non-system-row-badge">NON-SYSTEM</span>@endif
                            <small>{{ $row->item_name }}</small>
                            @if($isNonSystem)<small>Smallest UOM: {{ $row->smallest_uom_code ?: 'PCS' }}</small>@endif
                        </td>
                        <td class="audit-cell summary-action-col">
                            @if($cycle->status === \App\Models\StockOpnameCycle::STATUS_CLOSED)
                                <details class="override-editor action-override-editor">
                                    <summary class="btn small audit-button">{{ $hasOverride ? 'Audit / Edit' : 'Override / Audit' }}</summary>
                                    <div class="override-editor-body">
                                        <div class="audit-links">
                                            @if((int) $row->scan_count > 0)
                                                @if($isNonSystem)
                                                    <a href="{{ route('admin.cycles.scan-detail.non-system', [$cycle, $row->warehouse_id, $row->discovered_item_id]) }}">Lihat detail scan ({{ number_format($row->scan_count) }})</a>
                                                @else
                                                    <a href="{{ route('admin.cycles.scan-detail', [$cycle, $row->warehouse_id, $row->item_id]) }}">Lihat detail scan ({{ number_format($row->scan_count) }})</a>
                                                @endif
                                            @else
                                                <span class="muted">Tidak ada scan fisik</span>
                                            @endif
                                        </div>

                                        @if($hasOverride)
                                            <div class="override-audit">
                                                <strong>Override aktif: {{ number_format((float) $row->override_qty, 4, '.', ',') }} {{ $isNonSystem ? ($row->override_smallest_uom_code ?: $row->smallest_uom_code) : '' }}</strong>
                                                <small>{{ $row->override_by_name ?: 'Admin' }} · {{ $row->override_updated_at ? \Carbon\Carbon::parse($row->override_updated_at)->format('d/m/Y H:i:s') : '-' }}</small>
                                                @if($isNonSystem && $row->override_input_qty !== null)
                                                    <small>{{ number_format((float) $row->override_input_qty,4,'.',',') }} {{ $row->override_input_uom_code }} × {{ number_format((float) $row->override_ratio_used,4,'.',',') }}</small>
                                                @endif
                                                <p>{{ $row->override_comment }}</p>
                                            </div>
                                        @endif

                                        @if($isNonSystem)
                                            <form method="POST" action="{{ route('admin.cycles.summary.non-system.override', [$cycle, $row->warehouse_id, $row->discovered_item_id]) }}">
                                                @csrf @method('PUT')
                                                <div class="grid-2">
                                                    <label>Qty Override<input type="number" name="qty" min="0" step="0.0001" value="{{ $hasOverride ? (float) $row->override_input_qty : '' }}" required></label>
                                                    <label>UOM<input name="uom_code" maxlength="50" value="{{ $hasOverride ? $row->override_input_uom_code : ($row->smallest_uom_code ?: 'PCS') }}" required></label>
                                                    <label>Isi per UOM<input type="number" name="ratio_to_smallest" min="0.0001" step="0.0001" value="{{ $hasOverride ? (float) $row->override_ratio_used : 1 }}" required></label>
                                                    <label>Smallest UOM<input name="smallest_uom_code" maxlength="50" value="{{ $hasOverride ? $row->override_smallest_uom_code : ($row->smallest_uom_code ?: 'PCS') }}" required></label>
                                                </div>
                                                <label>Comment<textarea name="comment" rows="3" maxlength="2000" required>{{ $hasOverride ? $row->override_comment : '' }}</textarea></label>
                                                <button class="btn primary small" type="submit">Simpan Override</button>
                                            </form>
                                            @if($hasOverride)
                                                <form method="POST" action="{{ route('admin.cycles.summary.non-system.override.destroy', [$cycle, $row->warehouse_id, $row->discovered_item_id]) }}" onsubmit="return confirm('Hapus override Non-System? Jika ada scan, Final Fisik kembali memakai scan asli.');">
                                                    @csrf @method('DELETE')
                                                    <button class="danger-link" type="submit">Hapus Override</button>
                                                </form>
                                            @endif
                                        @else
                                            <form method="POST" action="{{ route('admin.cycles.summary.override', [$cycle, $row->warehouse_id, $row->item_id]) }}">
                                                @csrf @method('PUT')
                                                <label>Qty Override<input type="number" name="override_qty" min="0" step="0.0001" value="{{ $hasOverride ? (float) $row->override_qty : '' }}" required><small>Isi dalam smallest UOM yang sama dengan Opening.</small></label>
                                                <label>Comment<textarea name="comment" rows="3" maxlength="2000" required>{{ $hasOverride ? $row->override_comment : '' }}</textarea></label>
                                                <button class="btn primary small" type="submit">Simpan Override</button>
                                            </form>
                                            @if($hasOverride)
                                                <form method="POST" action="{{ route('admin.cycles.summary.override.destroy', [$cycle, $row->warehouse_id, $row->item_id]) }}" onsubmit="return confirm('Hapus override? Final fisik akan kembali memakai hasil scan asli.');">
                                                    @csrf @method('DELETE')
                                                    <button class="danger-link" type="submit">Hapus Override</button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </details>
                            @elseif($cycle->status === \App\Models\StockOpnameCycle::STATUS_FINALIZED)
                                <div class="final-audit-readonly">
                                    @if((int) $row->scan_count > 0)
                                        @if($isNonSystem)
                                            <a href="{{ route('admin.cycles.scan-detail.non-system', [$cycle, $row->warehouse_id, $row->discovered_item_id]) }}">Detail scan ({{ number_format($row->scan_count) }})</a>
                                        @else
                                            <a href="{{ route('admin.cycles.scan-detail', [$cycle, $row->warehouse_id, $row->item_id]) }}">Detail scan ({{ number_format($row->scan_count) }})</a>
                                        @endif
                                    @else
                                        <span class="muted">Tidak ada scan</span>
                                    @endif
                                    @if($hasOverride)
                                        <strong>Override Final: {{ number_format((float) $row->override_qty, 4, '.', ',') }}</strong>
                                        <small>{{ $row->override_by_name ?: 'Admin' }} · {{ $row->override_updated_at ? \Carbon\Carbon::parse($row->override_updated_at)->format('d/m/Y H:i:s') : '-' }}</small>
                                        <p>{{ $row->override_comment }}</p>
                                    @else
                                        <small class="final-lock-label">🔒 Tidak ada override</small>
                                    @endif
                                </div>
                            @else
                                <div class="audit-links">
                                    @if((int) $row->scan_count > 0)
                                        @if($isNonSystem)
                                            <a href="{{ route('admin.cycles.scan-detail.non-system', [$cycle, $row->warehouse_id, $row->discovered_item_id]) }}">Detail scan</a>
                                        @else
                                            <a href="{{ route('admin.cycles.scan-detail', [$cycle, $row->warehouse_id, $row->item_id]) }}">Detail scan</a>
                                        @endif
                                    @else
                                        <span class="muted">Belum ada scan</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="num">{{ number_format((float) $row->opening_system_qty, 4, '.', ',') }}</td>
                        <td class="num">{{ number_format((float) $row->physical_qty, 4, '.', ',') }}<small>{{ number_format($row->scan_count) }} scan</small></td>
                        <td class="num">@if($hasOverride)<strong class="override-value">{{ number_format((float) $row->override_qty, 4, '.', ',') }}</strong>@else<span class="muted">-</span>@endif</td>
                        <td class="num"><strong>{{ number_format((float) $row->final_physical_qty, 4, '.', ',') }}</strong>@if($hasOverride)<small class="override-label">OVERRIDE</small>@endif</td>
                        <td class="num {{ (float) $row->variance < 0 ? 'negative' : ((float) $row->variance > 0 ? 'positive' : '') }}"><strong>{{ number_format((float) $row->variance, 4, '.', ',') }}</strong></td>
                        <td class="num">{{ $movementKnown ? number_format((float) $row->closing_system_qty, 4, '.', ',') : '-' }}</td>
                        <td class="num">
                            @if(!$movementKnown)<span class="muted">Belum ada</span>
                            @elseif($hasMovement)<span class="movement">⚠ {{ number_format((float) $row->movement_qty, 4, '.', ',') }}</span>
                            @else<span class="ok-text">0</span>@endif
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
