@extends('layouts.app')
@section('title','Kelola Sampling Gudang')
@section('content')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/warehouse-sampling.css') }}?v=20261012integerallocation">
<style>
    .ws-allocation-card.is-short{background:#fff4f3!important;border-color:#fda29b!important;border-top:4px solid #d92d20!important}
    .ws-allocation-card.is-over{background:#eff8ff!important;border-color:#84caff!important;border-top:4px solid #1570ef!important}
    .ws-allocation-card.is-match{background:#f6fef9!important;border-color:#75e0a7!important;border-top:4px solid #079455!important}
    .ws-variance-edit{display:grid;gap:6px;margin-top:10px;padding:9px;border-radius:9px;background:#fff;border:1px solid #d0d5dd}
    .ws-variance-edit span{font-size:10px;font-weight:800;color:#344054}
    .ws-variance-edit input{width:100%;min-height:38px;border:1px solid #cfd6e2;border-radius:8px;padding:7px 9px;text-align:right;font-variant-numeric:tabular-nums}
    .ws-variance-edit small{font-size:9px;line-height:1.35;color:#667085}
    .ws-allocation-card.is-short .ws-variance-edit{border-color:#fda29b}
    .ws-allocation-card.is-over .ws-variance-edit{border-color:#84caff}
    .ws-allocation-card.is-match .ws-variance-edit{border-color:#75e0a7}
    .ws-allocation-total-warning{display:block;margin-top:8px;font-size:11px;font-weight:800;color:#b42318}
    .ws-allocation-total-warning.ok{color:#067647}
</style>
@endpush

@php
    $total = (int) $period->items_count;
    $checked = (int) $period->checked_items_count;
    $validated = (int) $period->validated_items_count;
    $pct = $total > 0 ? min(100, ($checked / $total) * 100) : 0;
    $targetCount = $total > 0 ? (int) ceil($total * ((float)$period->target_percentage / 100)) : 0;
    $reached = $targetCount > 0 && $checked >= $targetCount;

    $fmtQty = static function ($value): string {
        $number = round((float)($value ?? 0), 4);
        if (abs($number) < 0.0001) {
            $number = 0.0;
        }
        $formatted = number_format($number, 4, '.', ',');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        return $formatted === '-0' ? '0' : $formatted;
    };
@endphp

<div class="page-heading">
    <div>
        <h1>{{ $period->cycle_no }}</h1>
        <p>{{ $period->warehouses->count() }} gudang digabung · {{ $period->location }} · Checker {{ $period->checker?->name ?? '-' }}</p>
    </div>
    <a class="btn" href="{{ route('warehouse.admin.index') }}">← Daftar Periode</a>
</div>

<div class="ws-metrics">
    <div class="ws-metric"><span>Gudang Digabung</span><strong>{{ $period->warehouses->count() }}</strong></div>
    <div class="ws-metric"><span>Item Sampling</span><strong>{{ number_format($total) }}</strong></div>
    <div class="ws-metric"><span>Checker Selesai</span><strong>{{ number_format($checked) }}</strong></div>
    <div class="ws-metric"><span>Admin Validasi</span><strong>{{ number_format($validated) }}</strong></div>
</div>

<section class="panel">
    <div class="panel-head">
        <div><h2>Database & Gudang Periode</h2><small class="ws-note">Snapshot stok item disimpan terpisah untuk setiap gudang di bawah ini.</small></div>
        <span class="ws-badge {{ strtolower($period->status) }}">{{ $period->status }}</span>
    </div>
    <div class="ws-selected-warehouses">
        @foreach($period->warehouses as $warehouse)
            <div class="ws-selected-warehouse-card">
                <span>{{ $warehouse->source_database }}</span>
                <strong>{{ $warehouse->warehouse_code }}</strong>
                <small>{{ $warehouse->warehouse_name }}</small>
            </div>
        @endforeach
    </div>

    <div class="ws-progress {{ $reached?'target-reached':'' }}"><span style="width:{{ $pct }}%"></span></div>
    <p class="ws-note">Progress checker {{ $checked }} / {{ $total }} item ({{ number_format($pct,1) }}%). Target {{ number_format((float)$period->target_percentage,0) }}% = minimal {{ $targetCount }} item. {{ $reached ? 'Target tercapai.' : '' }}</p>
</section>

<section class="panel">
    <div class="panel-head"><h2>Pengaturan Periode</h2></div>
    <form method="POST" action="{{ route('warehouse.admin.update',$period) }}" class="stack-form">
        @csrf @method('PUT')
        <div class="ws-form-grid">
            <label>Target Checker (%)<input name="target_percentage" type="number" min="1" max="100" step="0.01" value="{{ old('target_percentage',$period->target_percentage) }}" required></label>
            <label>Checker Gudang<select name="assigned_checker_id" required>@foreach($checkers as $checker)<option value="{{ $checker->id }}" @selected((string)$period->assigned_checker_id===(string)$checker->id)>{{ $checker->name }} · {{ $checker->username }}</option>@endforeach</select></label>
        </div>
        <label>Area / Lokasi Fisik Gabungan<input name="location" value="{{ old('location',$period->location) }}" maxlength="255" required></label>
        <label>Catatan<textarea name="notes" rows="2">{{ old('notes',$period->notes) }}</textarea></label>
        @if(!$period->isClosed())<button class="btn" type="submit">Simpan Pengaturan</button>@endif
    </form>
</section>

@if($period->isDraft())
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Pilih Item dari Stok Gabungan</h2>
            <small class="ws-note">ItemCode yang sama dari AS_INGCO dan AS_SMI hanya tampil satu kali. Total stok adalah penjumlahan seluruh gudang terpilih; ItemID masing-masing database tetap disimpan pada snapshot gudang.</small>
        </div>
    </div>

    <form method="POST" action="{{ route('warehouse.admin.items.store',$period) }}" id="ws-bulk-item-form">
        @csrf
        <div id="ws-selected-inputs"></div>
        <div class="ws-stock-toolbar">
            <label class="ws-stock-search">Cari item<input type="search" id="ws-item-search" placeholder="Kode / nama item" autocomplete="off"></label>
            <label class="ws-stock-page-size">Baris<select id="ws-page-size"><option>25</option><option selected>50</option><option>100</option></select></label>
        </div>
        <div class="ws-stock-selectbar">
            <label class="ws-select-all"><input type="checkbox" id="ws-select-page"> Pilih semua di halaman ini</label>
            <span class="ws-note"><b id="ws-selected-count">0</b> item dipilih</span>
            <button class="btn primary" id="ws-save-items" type="submit" disabled>Simpan Item Terpilih</button>
        </div>
        <div class="ws-stock-table-wrap">
            <table class="ws-stock-table ws-stock-table-combined">
                <thead><tr><th class="ws-check-col"></th><th>Item</th><th>UOM</th><th class="num">Total Stok Gabungan</th><th>Sudah Dipilih</th></tr></thead>
                <tbody id="ws-stock-body"><tr><td colspan="5" class="empty">Memuat stok gabungan seluruh gudang...</td></tr></tbody>
            </table>
        </div>
        <div class="ws-stock-footer">
            <span class="ws-note" id="ws-stock-info"></span>
            <div class="ws-stock-pager"><button class="btn small" type="button" id="ws-prev-page">←</button><span class="ws-note" id="ws-page-label"></span><button class="btn small" type="button" id="ws-next-page">→</button></div>
        </div>
    </form>
</section>
@endif

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>{{ $period->isDraft() ? 'Item yang Sudah Dipilih' : 'Hasil Checker & Validasi Admin Gudang' }}</h2>
            <small class="ws-note">
                @if($period->isDraft())
                    Setiap ItemCode hanya satu baris walaupun terdapat di dua database. Snapshot per gudang tetap terpisah untuk validasi.
                @else
                    Checker mengisi Qty Fisik Total gabungan. Sistem membagi proporsional dalam bilangan bulat; Admin dapat mengedit Qty Selisih per gudang sebelum menyimpan validasi.
                @endif
            </small>
        </div>
    </div>

    <div class="ws-admin-item-list">
        @forelse($items as $item)
            @php
                $salesTotal = 0.0;
                $afterSalesTotal = (float) $item->system_qty;
                $pendingTransferTotal = 0.0;
                $validationSystemTotal = (float) $item->system_qty;

                if ($item->validated_at) {
                    $salesTotal = (float) $item->stocks->sum(fn($stock) => (float)($stock->sales_invoice_qty ?? 0));
                    $afterSalesTotal = (float) $item->stocks->sum(fn($stock) => (float)($stock->adjusted_system_qty ?? $stock->system_qty));
                    $pendingTransferTotal = (float) $item->stocks->sum(fn($stock) => (float)($stock->pending_transfer_qty ?? 0));
                    $validationSystemTotal = (float) $item->stocks->sum(fn($stock) => (float)($stock->validation_system_qty ?? (($stock->adjusted_system_qty ?? $stock->system_qty) + ($stock->pending_transfer_qty ?? 0))));
                } elseif ($item->checked_at) {
                    $salesTotal = (float) $item->stocks->sum(fn($stock) => (float)(($salesInvoiceAdjustments[$stock->id]['sales_qty'] ?? 0)));
                    $afterSalesTotal = (float) $item->stocks->sum(fn($stock) => (float)(($salesInvoiceAdjustments[$stock->id]['adjusted_system_qty'] ?? $stock->system_qty)));
                    $pendingTransferTotal = (float) $item->stocks->sum(fn($stock) => (float)(($pendingTransferAdjustments[$stock->id]['pending_transfer_qty'] ?? 0)));
                    $validationSystemTotal = (float) $item->stocks->sum(fn($stock) => (float)(($pendingTransferAdjustments[$stock->id]['validation_system_qty'] ?? ($salesInvoiceAdjustments[$stock->id]['adjusted_system_qty'] ?? $stock->system_qty))));
                }

                $totalVariance = ($item->checked_at === null || $item->physical_qty === null)
                    ? null
                    : (float)$item->physical_qty - $validationSystemTotal;
            @endphp
            <article class="ws-admin-item-card {{ $item->validated_at ? 'validated' : ($item->checked_at ? 'waiting-validation' : '') }}">
                <div class="ws-admin-item-head">
                    <div>
                        <span class="ws-line-number">#{{ $item->line_no }}</span>
                        <strong>{{ $item->item_code }}</strong>
                        <small>{{ $item->item_name }} · {{ $item->uom_code }}</small>
                    </div>
                    <div class="ws-admin-item-statuses">
                        @if($item->validated_at)
                            <span class="ws-badge open">VALIDATED</span>
                        @elseif($item->checked_at)
                            <span class="ws-badge draft">MENUNGGU VALIDASI</span>
                        @else
                            <span class="ws-badge">MENUNGGU CHECKER</span>
                        @endif
                    </div>
                </div>

                <div class="ws-total-strip ws-total-strip-sales">
                    <span>Snapshot Sistem<b>{{ $fmtQty($item->system_qty) }} {{ $item->uom_code }}</b></span>
                    <span>Sales Invoice (-)<b class="{{ $salesTotal > 0 ? 'negative' : '' }}">{{ $item->checked_at ? $fmtQty($salesTotal).' '.$item->uom_code : '-' }}</b></span>
                    <span>Pending GT (+)<b class="{{ $pendingTransferTotal > 0 ? 'positive' : '' }}">{{ $item->checked_at ? $fmtQty($pendingTransferTotal).' '.$item->uom_code : '-' }}</b></span>
                    <span>Stok Validasi<b>{{ $item->checked_at ? $fmtQty($validationSystemTotal).' '.$item->uom_code : '-' }}</b></span>
                    <span>Fisik Total Checker<b>{{ ($item->checked_at === null || $item->physical_qty === null) ? '-' : $fmtQty($item->physical_qty).' '.$item->uom_code }}</b></span>
                    <span>Selisih Total<b class="{{ $totalVariance !== null && $totalVariance < 0 ? 'negative' : ($totalVariance !== null && $totalVariance > 0 ? 'positive' : '') }}">{{ $totalVariance === null ? '-' : $fmtQty($totalVariance) }}</b></span>
                </div>

                @if($item->checked_at && filled($item->checker_comment))
                    <div class="ws-checker-comment-view">
                        <span>Komentar Checker</span>
                        <p>{{ $item->checker_comment }}</p>
                    </div>
                @endif

                @if($item->checked_at && !$period->isDraft())
                    @if($item->validated_at)
                        <div class="ws-allocation-horizontal-scroll" aria-label="Hasil alokasi fisik per gudang">
                            <div class="ws-allocation-horizontal-track">
                                @foreach($item->stocks as $stock)
                                    @php
                                        $stockSales = (float)($stock->sales_invoice_qty ?? 0);
                                        $stockAfterSales = (float)($stock->adjusted_system_qty ?? $stock->system_qty);
                                        $stockPendingGt = (float)($stock->pending_transfer_qty ?? 0);
                                        $stockValidation = (float)($stock->validation_system_qty ?? ($stockAfterSales + $stockPendingGt));
                                        $variance=(float)$stock->allocated_physical_qty-$stockValidation;
                                        $invoiceDetails = $stock->sales_invoice_details ?? [];
                                        $pendingTransferDetails = $stock->pending_transfer_details ?? [];
                                    @endphp
                                    <article class="ws-allocation-card {{ $variance<-.0001?'is-short':($variance>.0001?'is-over':'is-match') }}">
                                        <div class="ws-allocation-card-head">
                                            <span>{{ $stock->warehouse?->source_database }}</span>
                                            <span class="ws-badge {{ $stock->result==='MATCH'?'open':'closed' }}">{{ $stock->result==='MATCH'?'COCOK':($variance>0?'LEBIH':'KURANG') }}</span>
                                        </div>
                                        <strong class="ws-allocation-warehouse-code">{{ $stock->warehouse?->warehouse_code }}</strong>
                                        <small class="ws-allocation-warehouse-name">{{ $stock->warehouse?->warehouse_name }}</small>
                                        <div class="ws-allocation-card-metrics">
                                            <span>Snapshot Stok<b>{{ $fmtQty($stock->system_qty) }}</b></span>
                                            <span>Sales Invoice (-)<b class="{{ $stockSales > 0 ? 'negative' : '' }}">{{ $fmtQty($stockSales) }}</b></span>
                                            <span>Pending GT (+)<b class="{{ $stockPendingGt > 0 ? 'positive' : '' }}">{{ $fmtQty($stockPendingGt) }}</b></span>
                                            <span>Stok Validasi<b>{{ $fmtQty($stockValidation) }}</b></span>
                                            <span>Fisik Dialokasikan<b>{{ $fmtQty($stock->allocated_physical_qty) }}</b></span>
                                            <span>Selisih<b class="{{ $variance<0?'negative':($variance>0?'positive':'') }}">{{ $fmtQty($variance) }}</b></span>
                                        </div>
                                        @if($stockSales > 0)
                                            <div class="ws-sales-invoice-list">
                                                <strong>Sales Invoice {{ $stock->sales_invoice_date?->format('d/m/Y') }}</strong>
                                                @foreach($invoiceDetails as $invoice)
                                                    <span>{{ $invoice['invoice_number'] ?? '-' }} <b>{{ $fmtQty($invoice['qty'] ?? 0) }}</b></span>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if($stockPendingGt > 0)
                                            <div class="ws-pending-transfer-list">
                                                <strong>Good Transfer belum Received</strong>
                                                @foreach($pendingTransferDetails as $transfer)
                                                    <span>{{ $transfer['mutation_number'] ?? '-' }} · {{ !empty($transfer['mutation_date']) ? \Carbon\Carbon::parse($transfer['mutation_date'])->format('d/m/Y') : '-' }} · {{ $transfer['source_warehouse_code'] ?? '-' }} → {{ $transfer['destination_warehouse_code'] ?? '-' }} · {{ $fmtQty($transfer['document_qty'] ?? 0) }} {{ $transfer['uom_code'] ?? '' }} <b>+{{ $fmtQty($transfer['qty_smallest'] ?? 0) }}</b></span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        </div>
                        <p class="ws-note">Divalidasi oleh {{ $item->validator?->name ?? '-' }} · {{ $item->validated_at?->format('d/m/Y H:i:s') }} @if($item->validation_note) · {{ $item->validation_note }} @endif</p>
                    @else
                        <form method="POST" action="{{ route('warehouse.admin.items.validate',[$period,$item]) }}" class="ws-validation-form" data-physical-total="{{ (float)$item->physical_qty }}" data-system-total="{{ (float)$item->system_qty }}">
                            @csrf @method('PUT')
                            <div class="ws-validation-help">
                                <strong>Validasi Stok: Proporsional Bilangan Bulat + Koreksi Admin</strong>
                                <p>Stok validasi = <b>Snapshot - Sales Invoice Hari Cek + Good Transfer belum Received</b>. Sistem membagi Qty Fisik Checker secara proporsional tanpa desimal. Admin dapat mengubah <b>Qty Selisih</b>; total alokasi wajib tetap sama dengan Qty Fisik Checker.</p>
                            </div>
                            <div class="ws-allocation-horizontal-scroll" aria-label="Distribusi fisik proporsional per gudang">
                                <div class="ws-allocation-horizontal-track">
                                    @foreach($item->stocks as $stock)
                                        @php
                                            $salesInfo = $salesInvoiceAdjustments[$stock->id] ?? ['sales_date' => $item->checked_at?->format('Y-m-d'), 'sales_qty' => 0, 'adjusted_system_qty' => (float)$stock->system_qty, 'invoices' => []];
                                            $pendingInfo = $pendingTransferAdjustments[$stock->id] ?? ['pending_transfer_qty' => 0, 'validation_system_qty' => (float)($salesInfo['adjusted_system_qty'] ?? $stock->system_qty), 'transfers' => [], 'conversion_warnings' => []];
                                            $stockSales = (float)($salesInfo['sales_qty'] ?? 0);
                                            $stockAfterSales = (float)($salesInfo['adjusted_system_qty'] ?? $stock->system_qty);
                                            $stockPendingGt = (float)($pendingInfo['pending_transfer_qty'] ?? 0);
                                            $stockValidation = (float)($pendingInfo['validation_system_qty'] ?? ($stockAfterSales + $stockPendingGt));
                                            $canAllocate = $stock->item_id !== null;
                                        @endphp
                                        <article class="ws-allocation-card" data-stock-row data-stock-id="{{ $stock->id }}" data-system="{{ $stockValidation }}" data-can-allocate="{{ $canAllocate ? '1' : '0' }}">
                                            <div class="ws-allocation-card-head">
                                                <span>{{ $stock->warehouse?->source_database }}</span>
                                                <span class="ws-allocation-card-index">Gudang {{ $loop->iteration }}</span>
                                            </div>
                                            <strong class="ws-allocation-warehouse-code">{{ $stock->warehouse?->warehouse_code }}</strong>
                                            <small class="ws-allocation-warehouse-name">{{ $stock->warehouse?->warehouse_name }}</small>
                                            <div class="ws-allocation-card-metrics">
                                                <span>Snapshot Stok<b>{{ $fmtQty($stock->system_qty) }}</b></span>
                                                <span>Sales Invoice (-)<b class="{{ $stockSales > 0 ? 'negative' : '' }}">{{ $fmtQty($stockSales) }}</b></span>
                                                <span>Pending GT (+)<b class="{{ $stockPendingGt > 0 ? 'positive' : '' }}">{{ $fmtQty($stockPendingGt) }}</b></span>
                                                <span>Stok Validasi<b>{{ $fmtQty($stockValidation) }}</b></span>
                                                <span>Proporsi Stok<b data-proportion>-</b></span>
                                                <span>Fisik Dialokasikan<b data-proportional-qty>-</b></span>
                                                <span>Selisih Aktual<b data-row-variance>-</b></span>
                                            </div>

                                            @if($canAllocate)
                                                <label class="ws-variance-edit">
                                                    <span>Qty Selisih — bisa diedit Admin</span>
                                                    <input type="number" step="any" inputmode="decimal" data-variance-input value="0" autocomplete="off">
                                                    <small>Minus = kurang, plus = lebih. Sistem menjaga Fisik Dialokasikan tetap bilangan bulat.</small>
                                                </label>
                                                <input type="hidden" name="allocation_qty[{{ $stock->id }}]" value="0" data-allocation-input>
                                            @else
                                                <div class="ws-variance-edit"><span>Qty Selisih</span><small>ItemCode ini tidak tersedia pada database gudang tersebut sehingga tidak dapat dialokasikan.</small></div>
                                            @endif

                                            @if($stockSales > 0)
                                                <div class="ws-sales-invoice-list">
                                                    <strong>Sales Invoice {{ !empty($salesInfo['sales_date']) ? \Carbon\Carbon::parse($salesInfo['sales_date'])->format('d/m/Y') : '' }}</strong>
                                                    @foreach(($salesInfo['invoices'] ?? []) as $invoice)
                                                        <span>{{ $invoice['invoice_number'] ?? '-' }} <b>{{ $fmtQty($invoice['qty'] ?? 0) }}</b></span>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if($stockPendingGt > 0)
                                                <div class="ws-pending-transfer-list">
                                                    <strong>Good Transfer belum Received</strong>
                                                    @foreach(($pendingInfo['transfers'] ?? []) as $transfer)
                                                        <span>{{ $transfer['mutation_number'] ?? '-' }} · {{ !empty($transfer['mutation_date']) ? \Carbon\Carbon::parse($transfer['mutation_date'])->format('d/m/Y') : '-' }} · {{ $transfer['source_warehouse_code'] ?? '-' }} → {{ $transfer['destination_warehouse_code'] ?? '-' }} · {{ $fmtQty($transfer['document_qty'] ?? 0) }} {{ $transfer['uom_code'] ?? '' }} <b>+{{ $fmtQty($transfer['qty_smallest'] ?? 0) }}</b></span>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if(!empty($pendingInfo['conversion_warnings']))
                                                <div class="ws-transfer-warning">
                                                    @foreach($pendingInfo['conversion_warnings'] as $warning)<span>{{ $warning }}</span>@endforeach
                                                </div>
                                            @endif
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                            <div class="ws-allocation-summary ws-allocation-summary-sales">
                                <span>Snapshot Sistem <b>{{ $fmtQty($item->system_qty) }}</b></span>
                                <span>Sales Invoice (-) <b class="{{ $salesTotal > 0 ? 'negative' : '' }}">{{ $fmtQty($salesTotal) }}</b></span>
                                <span>Pending GT (+) <b class="{{ $pendingTransferTotal > 0 ? 'positive' : '' }}">{{ $fmtQty($pendingTransferTotal) }}</b></span>
                                <span>Stok Validasi <b>{{ $fmtQty($validationSystemTotal) }}</b></span>
                                <span>Fisik Checker <b>{{ $fmtQty($item->physical_qty) }}</b></span>
                                <span>Total Distribusi <b data-allocation-total>0</b></span>
                            </div>
                            <span class="ws-allocation-total-warning" data-allocation-warning></span>
                            <label class="ws-validation-note">Catatan Validasi<textarea name="validation_note" rows="2" placeholder="Opsional: catatan hasil validasi"></textarea></label>
                            <button class="btn primary" type="submit" data-validation-save>Simpan Validasi</button>
                        </form>
                    @endif
                @else
                    <div class="ws-allocation-horizontal-scroll ws-snapshot-horizontal-scroll" aria-label="Snapshot stok per gudang">
                        <div class="ws-allocation-horizontal-track">
                            @foreach($item->stocks as $stock)
                                <article class="ws-allocation-card ws-snapshot-card">
                                    <div class="ws-allocation-card-head"><span>{{ $stock->warehouse?->source_database }}</span></div>
                                    <strong class="ws-allocation-warehouse-code">{{ $stock->warehouse?->warehouse_code }}</strong>
                                    <small class="ws-allocation-warehouse-name">{{ $stock->warehouse?->warehouse_name }}</small>
                                    <div class="ws-allocation-system-row"><span>Snapshot Stok</span><b>{{ $fmtQty($stock->system_qty) }}</b></div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($period->isDraft())
                    <form method="POST" action="{{ route('warehouse.admin.items.destroy',[$period,$item]) }}" onsubmit="return confirm('Hapus baris item ini?')">@csrf @method('DELETE')<button class="btn small danger" type="submit">Hapus Item</button></form>
                @endif
            </article>
        @empty
            <div class="empty">Belum ada item pada periode ini.</div>
        @endforelse
    </div>

    {{ $items->links() }}
</section>

<div class="ws-actions">
    @if($period->isDraft())
        <form method="POST" action="{{ route('warehouse.admin.release',$period) }}" onsubmit="return confirm('Serahkan daftar ini ke Checker Gudang? Daftar database, gudang, dan item akan dikunci.')">@csrf<button class="btn primary" type="submit">Serahkan ke Checker</button></form>
    @elseif(!$period->isClosed())
        <form method="POST" action="{{ route('warehouse.admin.close',$period) }}" onsubmit="return confirm('Tutup periode ini?')">@csrf<button class="btn danger" type="submit">Tutup Periode</button></form>
    @endif
</div>
@endsection

@push('scripts')
@if($period->isDraft())
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const url=@json(route('warehouse.admin.items.available',$period));
    const body=document.getElementById('ws-stock-body');
    const search=document.getElementById('ws-item-search');
    const perPage=document.getElementById('ws-page-size');
    const selectPage=document.getElementById('ws-select-page');
    const selectedCount=document.getElementById('ws-selected-count');
    const selectedInputs=document.getElementById('ws-selected-inputs');
    const saveBtn=document.getElementById('ws-save-items');
    const info=document.getElementById('ws-stock-info');
    const pageLabel=document.getElementById('ws-page-label');
    const prev=document.getElementById('ws-prev-page');
    const next=document.getElementById('ws-next-page');
    const form=document.getElementById('ws-bulk-item-form');
    const selected=new Map();
    let currentRows=[]; let page=1; let lastPage=1; let timer=null; let loading=false;

    const escapeHtml=v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
    const formatQty=v=>Number(v||0).toLocaleString('id-ID',{minimumFractionDigits:0,maximumFractionDigits:4});

    function syncSelected(){
        selectedCount.textContent=selected.size; saveBtn.disabled=selected.size===0; selectedInputs.innerHTML='';
        selected.forEach((_,key)=>{const input=document.createElement('input');input.type='hidden';input.name='item_keys[]';input.value=key;selectedInputs.appendChild(input);});
        const keys=currentRows.map(i=>String(i.key));
        selectPage.checked=keys.length>0&&keys.every(key=>selected.has(key));
        selectPage.indeterminate=keys.some(key=>selected.has(key))&&!selectPage.checked;
    }

    function render(rows){
        currentRows=rows; body.innerHTML='';
        if(!rows.length){body.innerHTML='<tr><td colspan="5" class="empty">Tidak ada item stok gabungan yang cocok.</td></tr>';syncSelected();return;}
        rows.forEach(item=>{
            const key=String(item.key); const tr=document.createElement('tr'); if(selected.has(key))tr.classList.add('selected');
            const picked=item.selected_count>0?`<span class="ws-picked-badge">${item.selected_count}x</span>`:'<span class="muted">Belum</span>';
            tr.innerHTML=`<td class="ws-check-col"><input type="checkbox" class="ws-item-check" ${selected.has(key)?'checked':''}></td><td><strong>${escapeHtml(item.item_code)}</strong><small>${escapeHtml(item.item_name)}</small></td><td>${escapeHtml(item.uom_code)}</td><td class="num"><strong>${formatQty(item.system_qty)}</strong></td><td>${picked}</td>`;
            const checkbox=tr.querySelector('.ws-item-check');
            checkbox.addEventListener('change',()=>{if(checkbox.checked)selected.set(key,item);else selected.delete(key);tr.classList.toggle('selected',checkbox.checked);syncSelected();});
            tr.addEventListener('click',e=>{if(e.target===checkbox||e.target.closest('button,a,input,label'))return;checkbox.checked=!checkbox.checked;checkbox.dispatchEvent(new Event('change'));});
            body.appendChild(tr);
        });
        syncSelected();
    }

    async function load(targetPage=1){
        if(loading)return; loading=true; body.innerHTML='<tr><td colspan="5" class="empty">Memuat stok gabungan seluruh gudang...</td></tr>'; prev.disabled=true;next.disabled=true;
        try{
            const params=new URLSearchParams({page:String(targetPage),per_page:perPage.value});const q=search.value.trim();if(q)params.set('q',q);
            const response=await fetch(`${url}?${params.toString()}`,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});const json=await response.json();
            if(!response.ok)throw new Error(json?.message||'Gagal membaca stok gabungan.');
            page=json.meta?.page||1;lastPage=json.meta?.last_page||1;render(json.data||[]);
            info.textContent=`Menampilkan ${json.meta?.from||0}-${json.meta?.to||0} dari ${json.meta?.total||0} ItemCode unik dengan total stok gabungan > 0`;
            pageLabel.textContent=`Halaman ${page} / ${lastPage}`;prev.disabled=page<=1;next.disabled=page>=lastPage;
        }catch(e){currentRows=[];body.innerHTML=`<tr><td colspan="5" class="empty">${escapeHtml(e.message||'Gagal mengambil stok ERP.')}</td></tr>`;info.textContent='';pageLabel.textContent='';}
        finally{loading=false;}
    }

    selectPage.addEventListener('change',()=>{currentRows.forEach(item=>{const key=String(item.key);if(selectPage.checked)selected.set(key,item);else selected.delete(key);});render(currentRows);});
    search.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>load(1),300);});perPage.addEventListener('change',()=>load(1));prev.addEventListener('click',()=>page>1&&load(page-1));next.addEventListener('click',()=>page<lastPage&&load(page+1));
    form.addEventListener('submit',e=>{if(selected.size===0){e.preventDefault();return;}saveBtn.disabled=true;saveBtn.textContent=`Menyimpan ${selected.size} item...`;});
    load(1);
});
</script>
@endif
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const qty=v=>Number(v||0).toLocaleString('id-ID',{minimumFractionDigits:0,maximumFractionDigits:4});
    const plain=v=>{
        const n=Math.abs(Number(v||0))<0.0001?0:Math.round(Number(v||0)*10000)/10000;
        return String(n);
    };

    document.querySelectorAll('.ws-validation-form').forEach(form=>{
        const physicalRaw=Number(form.dataset.physicalTotal||0);
        const physical=Math.round(physicalRaw);
        const physicalIsWhole=Math.abs(physicalRaw-physical)<0.0001;
        const rows=[...form.querySelectorAll('[data-stock-row]')];
        const weighted=rows.filter(row=>row.dataset.canAllocate==='1'&&Number(row.dataset.system||0)>0);
        const systemTotal=weighted.reduce((sum,row)=>sum+Number(row.dataset.system||0),0);
        const saveButton=form.querySelector('[data-validation-save]');
        const totalEl=form.querySelector('[data-allocation-total]');
        const warning=form.querySelector('[data-allocation-warning]');

        const setCardState=(row,variance)=>{
            row.classList.toggle('is-short',variance<-.0001);
            row.classList.toggle('is-over',variance>.0001);
            row.classList.toggle('is-match',Math.abs(variance)<=.0001);
        };

        const applyAllocation=(row,value,syncVariance=true)=>{
            const allocationInput=row.querySelector('[data-allocation-input]');
            if(!allocationInput)return;
            const system=Number(row.dataset.system||0);
            const allocation=Math.max(0,Math.round(Number(value||0)));
            allocationInput.value=String(allocation);
            const allocationLabel=row.querySelector('[data-proportional-qty]');
            if(allocationLabel)allocationLabel.textContent=qty(allocation);
            const variance=allocation-system;
            const varianceLabel=row.querySelector('[data-row-variance]');
            if(varianceLabel){
                varianceLabel.textContent=qty(variance);
                varianceLabel.classList.toggle('negative',variance<-.0001);
                varianceLabel.classList.toggle('positive',variance>.0001);
            }
            const varianceInput=row.querySelector('[data-variance-input]');
            if(varianceInput&&syncVariance)varianceInput.value=plain(variance);
            setCardState(row,variance);
        };

        const updateTotal=()=>{
            const allocated=rows.reduce((sum,row)=>{
                const input=row.querySelector('[data-allocation-input]');
                return sum+(input?Number(input.value||0):0);
            },0);
            if(totalEl)totalEl.textContent=qty(allocated);

            const valid=physicalIsWhole&&allocated===physical;
            if(warning){
                warning.classList.toggle('ok',valid);
                if(!physicalIsWhole){
                    warning.textContent='Qty Fisik Checker masih mengandung desimal. Checker harus mengubahnya menjadi bilangan bulat sebelum validasi.';
                }else if(valid){
                    warning.textContent=`Total distribusi sudah sesuai Qty Fisik Checker: ${qty(physical)}.`;
                }else{
                    warning.textContent=`Total distribusi ${qty(allocated)} belum sama dengan Qty Fisik Checker ${qty(physical)}. Edit Qty Selisih pada gudang terkait.`;
                }
            }
            if(saveButton)saveButton.disabled=!valid;
        };

        // Largest remainder: floor semua quota, kemudian sisa unit diberikan ke
        // fractional remainder terbesar. Hasil selalu integer dan totalnya tepat.
        const quotas=weighted.map(row=>{
            const system=Number(row.dataset.system||0);
            const exact=systemTotal>0?(physical*(system/systemTotal)):0;
            return {row,system,exact,base:Math.floor(exact),fraction:exact-Math.floor(exact)};
        });
        let baseTotal=quotas.reduce((sum,q)=>sum+q.base,0);
        let remaining=Math.max(0,physical-baseTotal);
        quotas.sort((a,b)=>b.fraction-a.fraction||Number(a.row.dataset.stockId)-Number(b.row.dataset.stockId));
        quotas.forEach((q,index)=>{q.value=q.base+(index<remaining?1:0);});

        rows.forEach(row=>{
            const system=Number(row.dataset.system||0);
            const proportion=systemTotal>0&&system>0?(system/systemTotal)*100:0;
            const prop=row.querySelector('[data-proportion]');
            if(prop)prop.textContent=proportion.toLocaleString('id-ID',{minimumFractionDigits:0,maximumFractionDigits:2})+'%';

            const quota=quotas.find(q=>q.row===row);
            applyAllocation(row,quota?quota.value:0,true);

            const varianceInput=row.querySelector('[data-variance-input]');
            if(varianceInput){
                varianceInput.addEventListener('change',()=>{
                    const desiredVariance=Number(varianceInput.value||0);
                    const desiredAllocation=Math.max(0,Math.round(system+desiredVariance));
                    applyAllocation(row,desiredAllocation,true);
                    updateTotal();
                });
            }
        });

        updateTotal();
        form.addEventListener('submit',event=>{
            const allocated=rows.reduce((sum,row)=>{
                const input=row.querySelector('[data-allocation-input]');
                return sum+(input?Number(input.value||0):0);
            },0);
            if(!physicalIsWhole||allocated!==physical){
                event.preventDefault();
                updateTotal();
            }
        });
    });
});
</script>
@endpush
