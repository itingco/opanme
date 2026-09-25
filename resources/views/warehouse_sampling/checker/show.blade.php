@extends('layouts.app')
@section('title','Cek Qty Fisik Total')
@section('content')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/warehouse-sampling.css') }}?v=20260924a">
@endpush
@php
    $total = (int) $period->items_count;
    $filled = (int) ($period->filled_items_count ?? 0);
    $checked = (int) $period->checked_items_count;
    $finalized = $total > 0 && $checked === $total;
    $editable = $period->isOpen() && !$finalized;
    $pct = $total > 0 ? min(100, ($filled / $total) * 100) : 0;
    $targetCount = $total > 0 ? (int) ceil($total * ((float)$period->target_percentage / 100)) : 0;
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
    <div class="ws-progress"><span style="width:{{ $pct }}%"></span></div>
    <div class="ws-checker-progress-meta">
        <p class="ws-note">{{ $filled }} / {{ $total }} item sudah memiliki draft Qty Fisik ({{ number_format($pct,1) }}%).</p>
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
    <div class="ws-final-lock-note"><strong>Hasil checker sudah final.</strong> Semua Qty dan komentar dikunci. Admin Gudang dapat melanjutkan validasi.</div>
@elseif($editable)
    <div class="ws-lock-note"><strong>Klik kode barang untuk input.</strong> Setiap item disimpan satu per satu sebagai draft melalui modal. Draft masih bisa diedit sampai Anda menekan <b>Finalisasi Semua Item</b>.</div>
@endif

<section class="panel ws-modal-input-panel">
    <div class="panel-head">
        <div><h2>Daftar Item</h2><small>Klik kode item untuk isi / edit Qty Fisik dan komentar.</small></div>
        @if($editable)
            <form method="POST" action="{{ route('warehouse.checker.finalize',$period) }}" onsubmit="return confirm('Finalisasi semua item? Setelah finalisasi Qty dan komentar tidak dapat diedit lagi.')">
                @csrf
                <button class="btn primary" type="submit" @disabled($filled < $total || $total === 0)>Finalisasi Semua Item</button>
            </form>
        @endif
    </div>

    <div class="ws-check-table-wrap ws-modal-check-table-wrap">
        <table class="ws-check-input-table">
            <thead><tr><th>No</th><th>Kode Item</th><th>Nama Item</th><th>UOM</th><th class="num">Qty Fisik Draft</th><th>Komentar</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($items as $item)
                @php($hasDraft = $item->physical_qty !== null)
                <tr class="{{ $finalized ? 'is-final' : ($hasDraft ? 'is-draft' : '') }}">
                    <td><strong>#{{ $item->line_no }}</strong></td>
                    <td>
                        @if($editable)
                            <button type="button" class="ws-item-code-button"
                                data-open-item-modal
                                data-action="{{ route('warehouse.checker.check',[$period,$item]) }}"
                                data-line="{{ $item->line_no }}"
                                data-code="{{ $item->item_code }}"
                                data-name="{{ $item->item_name }}"
                                data-uom="{{ $item->uom_code }}"
                                data-qty="{{ $item->physical_qty }}"
                                data-comment="{{ $item->checker_comment }}">{{ $item->item_code }}</button>
                        @else
                            <strong>{{ $item->item_code }}</strong>
                        @endif
                    </td>
                    <td><strong>{{ $item->item_name }}</strong><small>{{ $item->stocks_count }} gudang terkait</small></td>
                    <td><span class="ws-uom-pill">{{ $item->uom_code }}</span></td>
                    <td class="num"><strong>{{ $hasDraft ? number_format((float)$item->physical_qty,4,'.',',') : '-' }}</strong></td>
                    <td><div class="ws-table-comment-preview">{{ filled($item->checker_comment) ? $item->checker_comment : '-' }}</div></td>
                    <td>
                        @if($finalized)<span class="ws-badge open">FINAL</span>
                        @elseif($hasDraft)<span class="ws-badge draft">DRAFT</span>
                        @else<span class="ws-badge">BELUM</span>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Tidak ada item dalam periode ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($editable)
        <div class="ws-modal-table-footer">
            <span><strong>{{ $filled }} / {{ $total }}</strong> item sudah disimpan sebagai draft.</span>
            <span>{{ max(0,$total-$filled) }} item belum diisi.</span>
        </div>
    @endif
</section>

@if($editable)
<div class="ws-item-entry-modal" id="ws-item-entry-modal" hidden>
    <div class="ws-item-entry-backdrop" data-item-modal-close></div>
    <section class="ws-item-entry-dialog" role="dialog" aria-modal="true" aria-labelledby="ws-item-modal-title">
        <header>
            <div><span class="ws-modal-kicker">INPUT DRAFT CHECKER</span><h2 id="ws-item-modal-title">Item</h2><p id="ws-item-modal-name">-</p></div>
            <button type="button" class="ws-item-entry-close" data-item-modal-close>×</button>
        </header>
        <form method="POST" id="ws-item-entry-form" class="stack-form">@csrf @method('PUT')
            <div class="ws-modal-item-meta"><span id="ws-item-modal-line">#-</span><span id="ws-item-modal-uom">UOM</span></div>
            <label>Qty Fisik Total Ditemukan
                <input type="number" name="physical_qty" id="ws-item-modal-qty" min="0" step="0.0001" inputmode="decimal" required autocomplete="off" placeholder="0.0000">
            </label>
            <label>Komentar Checker
                <textarea name="checker_comment" id="ws-item-modal-comment" rows="4" maxlength="1000" placeholder="Opsional: kondisi fisik, lokasi, kemasan, catatan..."></textarea>
            </label>
            <div class="ws-item-entry-actions"><button type="button" class="btn" data-item-modal-close>Batal</button><button type="submit" class="btn primary">Simpan Draft Item</button></div>
            <small class="ws-note">Menyimpan draft tidak mengunci data. Klik kode item lagi jika ingin mengubah sebelum finalisasi.</small>
        </form>
    </section>
</div>
@endif
@endsection

@push('scripts')
@if($editable)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('ws-item-entry-modal');
    const form = document.getElementById('ws-item-entry-form');
    const title = document.getElementById('ws-item-modal-title');
    const name = document.getElementById('ws-item-modal-name');
    const line = document.getElementById('ws-item-modal-line');
    const uom = document.getElementById('ws-item-modal-uom');
    const qty = document.getElementById('ws-item-modal-qty');
    const comment = document.getElementById('ws-item-modal-comment');
    if (!modal || !form) return;

    const open = button => {
        form.action = button.dataset.action;
        title.textContent = button.dataset.code || 'Item';
        name.textContent = button.dataset.name || '-';
        line.textContent = `#${button.dataset.line || '-'}`;
        uom.textContent = button.dataset.uom || '-';
        qty.value = button.dataset.qty || '';
        comment.value = button.dataset.comment || '';
        modal.hidden = false;
        document.body.classList.add('modal-open');
        setTimeout(() => qty.focus(), 30);
    };
    const close = () => { modal.hidden = true; document.body.classList.remove('modal-open'); };
    document.querySelectorAll('[data-open-item-modal]').forEach(button => button.addEventListener('click', () => open(button)));
    document.querySelectorAll('[data-item-modal-close]').forEach(button => button.addEventListener('click', close));
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !modal.hidden) close(); });
});
</script>
@endif
@endpush
