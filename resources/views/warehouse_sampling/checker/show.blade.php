@extends('layouts.app')
@section('title','Cek Qty Fisik Total')
@section('content')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/warehouse-sampling.css') }}?v=20260923j">
@endpush
@php
    $total = (int) $period->items_count;
    $filled = (int) ($period->filled_items_count ?? 0);
    $checked = (int) $period->checked_items_count;
    $finalized = $total > 0 && $checked === $total;
    $editable = $period->isOpen() && !$finalized;
    $pct = $total > 0 ? min(100, ($filled / $total) * 100) : 0;
    $targetCount = $total > 0 ? (int) ceil($total * ((float)$period->target_percentage / 100)) : 0;
    $reached = $targetCount > 0 && $filled >= $targetCount;
@endphp

<div class="page-heading">
    <div>
        <h1>{{ $period->cycle_no }}</h1>
        <p>{{ $period->warehouses->count() }} gudang digabung · {{ $period->location }}</p>
    </div>
    <a class="btn" href="{{ route('warehouse.checker.index') }}">← Tugas</a>
</div>

<section class="panel">
    <div class="ws-checker-warehouse-list">
        @foreach($period->warehouses as $warehouse)
            <span><b>{{ $warehouse->source_database }} · {{ $warehouse->warehouse_code }}</b>{{ $warehouse->warehouse_name }}</span>
        @endforeach
    </div>
    <div class="ws-progress {{ $reached?'target-reached':'' }}"><span style="width:{{ $pct }}%"></span></div>
    <div class="ws-checker-progress-meta">
        <p class="ws-note"><span id="ws-filled-top">{{ $filled }}</span> / {{ $total }} item terisi ({{ number_format($pct,1) }}%). Target periode {{ number_format((float)$period->target_percentage,0) }}% = minimal {{ $targetCount }} item.</p>
        @if($finalized)
            <span class="ws-badge open">FINAL · TERKUNCI</span>
        @elseif($period->isOpen())
            <span class="ws-badge draft">DRAFT · BISA DIEDIT</span>
        @else
            <span class="ws-badge closed">CLOSED</span>
        @endif
    </div>
</section>

@if($finalized)
    <div class="ws-final-lock-note">
        <strong>Hasil checker sudah difinalisasi.</strong>
        Qty Fisik Total dan Komentar Checker sudah dikunci dan tidak dapat diedit lagi. Admin Gudang dapat melanjutkan validasi.
    </div>
@elseif($period->isOpen())
    <div class="ws-lock-note">
        <strong>Isi Qty Fisik dan komentar langsung di tabel.</strong>
        Tombol <b>Simpan Draft Semua</b> menyimpan seluruh Qty dan komentar tanpa mengunci data. Setelah semua Qty benar, pilih <b>Simpan Semua & Finalisasi</b> agar hasil checker dikunci.
    </div>
@else
    <div class="alert">Periode sudah CLOSED. Data hanya dapat dilihat.</div>
@endif

@if($editable)
<form method="POST" action="{{ route('warehouse.checker.batch',$period) }}" class="ws-check-batch-form" id="ws-check-batch-form">
    @csrf
    @method('PUT')
@endif

<div class="ws-check-batch-toolbar">
    <div>
        <strong>Input Qty Fisik Total & Komentar</strong>
        <small><span id="ws-filled-count">{{ $filled }}</span> dari {{ $total }} item sudah memiliki Qty Fisik</small>
    </div>
    @if($editable)
        <span class="ws-note">Qty dan komentar disimpan bersamaan.</span>
    @endif
</div>

<div class="ws-check-table-wrap">
    <table class="ws-check-input-table">
        <thead>
            <tr>
                <th class="ws-check-no-col">No</th>
                <th>Item</th>
                <th class="ws-check-uom-col">UOM</th>
                <th class="ws-check-qty-col">Qty Fisik Total</th>
                <th class="ws-check-comment-col">Komentar Checker</th>
                <th class="ws-check-status-col">Status</th>
            </tr>
        </thead>
        <tbody>
        @forelse($items as $item)
            @php
                $hasDraft = $item->physical_qty !== null;
                $inputValue = old('physical_qty.'.$item->id, $item->physical_qty);
                $commentValue = old('checker_comment.'.$item->id, $item->checker_comment);
            @endphp
            <tr class="{{ $finalized ? 'is-final' : ($hasDraft ? 'is-draft' : '') }}">
                <td class="ws-check-no-col"><strong>#{{ $item->line_no }}</strong></td>
                <td>
                    <strong class="ws-check-item-code">{{ $item->item_code }}</strong>
                    <small class="ws-check-item-name">{{ $item->item_name }}</small>
                    <small class="ws-note">Stok gabungan {{ $item->stocks_count }} gudang</small>
                </td>
                <td><span class="ws-uom-pill">{{ $item->uom_code }}</span></td>
                <td>
                    @if($editable)
                        <input
                            class="ws-table-qty-input"
                            type="number"
                            name="physical_qty[{{ $item->id }}]"
                            min="0"
                            step="0.0001"
                            inputmode="decimal"
                            value="{{ $inputValue }}"
                            placeholder="0.0000"
                            data-batch-qty
                            autocomplete="off">
                    @else
                        <strong class="ws-final-qty">{{ $item->physical_qty === null ? '-' : number_format((float)$item->physical_qty,4,'.',',') }}</strong>
                    @endif
                </td>
                <td>
                    @if($editable)
                        <textarea
                            class="ws-table-comment-input"
                            name="checker_comment[{{ $item->id }}]"
                            rows="2"
                            maxlength="1000"
                            placeholder="Opsional: kondisi fisik, lokasi, kemasan, catatan selisih...">{{ $commentValue }}</textarea>
                    @else
                        <div class="ws-final-comment">{{ filled($item->checker_comment) ? $item->checker_comment : '-' }}</div>
                    @endif
                </td>
                <td>
                    @if($finalized)
                        <span class="ws-badge open">FINAL</span>
                    @elseif($hasDraft)
                        <span class="ws-badge draft">DRAFT</span>
                    @else
                        <span class="ws-badge">BELUM</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">Tidak ada item dalam periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if($editable)
    <div class="ws-check-batch-actions">
        <div class="ws-check-batch-status">
            <strong id="ws-batch-status-title">{{ $filled }} / {{ $total }} item terisi</strong>
            <small id="ws-batch-status-note">Draft Qty dan komentar dapat disimpan berkali-kali sampai seluruh hasil benar.</small>
        </div>
        <div class="ws-check-batch-buttons">
            <button class="btn" type="submit" name="mode" value="draft">Simpan Draft Semua</button>
            <button
                class="btn primary"
                type="submit"
                name="mode"
                value="final"
                id="ws-finalize-all"
                @disabled($filled < $total || $total === 0)
                onclick="return confirm('Finalisasi seluruh Qty Fisik dan Komentar Checker? Setelah proses ini data tidak dapat diedit lagi.')">
                Simpan Semua & Finalisasi
            </button>
        </div>
    </div>
</form>
@endif
@endsection

@push('scripts')
@if($editable)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const inputs = Array.from(document.querySelectorAll('[data-batch-qty]'));
    const filledCount = document.getElementById('ws-filled-count');
    const filledTop = document.getElementById('ws-filled-top');
    const statusTitle = document.getElementById('ws-batch-status-title');
    const statusNote = document.getElementById('ws-batch-status-note');
    const finalButton = document.getElementById('ws-finalize-all');
    const total = inputs.length;

    function refresh() {
        const filled = inputs.filter(input => String(input.value ?? '').trim() !== '').length;
        if (filledCount) filledCount.textContent = filled;
        if (filledTop) filledTop.textContent = filled;
        if (statusTitle) statusTitle.textContent = `${filled} / ${total} item terisi`;
        if (finalButton) finalButton.disabled = total === 0 || filled !== total;
        if (statusNote) {
            statusNote.textContent = filled === total && total > 0
                ? 'Semua Qty sudah terisi. Komentar tetap opsional. Anda dapat melakukan finalisasi.'
                : `${Math.max(0, total - filled)} Qty masih belum diisi. Qty dan komentar tetap dapat disimpan sebagai draft.`;
        }
    }

    inputs.forEach(input => input.addEventListener('input', refresh));
    refresh();
});
</script>
@endif
@endpush
