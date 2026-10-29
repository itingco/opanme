@extends('label.layout')
@section('title', 'Cetak Label QR')
@push('head')
<style>
.label-unit-options{display:grid;gap:9px;margin:8px 0 12px}.label-unit-option{display:flex;align-items:center;gap:10px;padding:12px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;cursor:pointer}.label-unit-option:has(input:checked){background:#eff6ff;border-color:#2563eb}.label-unit-option input{width:19px;height:19px}.label-unit-option strong{display:block}.label-unit-option small{display:block;overflow-wrap:anywhere;color:#475569}.label-size-options{display:flex;gap:12px;flex-wrap:wrap}.label-size-option{display:flex;gap:8px;align-items:center;padding:12px;border:1px solid #cbd5e1;border-radius:10px;background:#fff}.label-size-fieldset{border:0;padding:0;margin:0}.label-size-fieldset legend{font-weight:700;margin-bottom:8px}.label-message{padding:10px;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;color:#475569}
</style>
@endpush
@section('content')
<main class="page-shell"><section class="entry-card" aria-labelledby="page-title">
<div class="entry-card__header"><div><p class="eyebrow">LABEL BARANG</p><h1 id="page-title">Cetak Label QR</h1><p class="subtitle">Cari kode / nama barang, pilih barcode sesuai satuan, lalu preview dan cetak.</p></div><div class="label-size-chip">2 Ukuran</div></div>
@if($errors->any())<div class="alert alert--error" role="alert"><strong>Label belum dapat dibuat.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('label.preview') }}" class="label-form" id="label-form" novalidate>@csrf
<div class="field field--lookup"><label for="item_code">Cari nama atau kode barang</label><div class="lookup-wrap"><input id="item_code" name="item_code" type="text" value="{{ old('item_code') }}" maxlength="100" autocomplete="off" placeholder="Ketik nama / kode barang" aria-autocomplete="list" aria-controls="item-results" required><span class="lookup-status" id="lookup-status" aria-live="polite"></span><div class="lookup-results" id="item-results" role="listbox" hidden></div></div><small>Pilih barang dari daftar pencarian.</small></div>
<div class="field"><label for="item_name">Nama barang</label><input id="item_name" type="text" readonly tabindex="-1" placeholder="Muncul otomatis setelah barang dipilih"></div>
<div class="field"><label>Satuan / Barcode tersedia</label><div id="barcode-options" class="label-unit-options" aria-live="polite"><div class="label-message">Pilih barang terlebih dahulu.</div></div><small id="barcode-help">Satuan dan barcode diambil dari DB_AppHub.ItemBarcode. Bila tidak ada, QR lama kode-qty akan digunakan.</small></div>
<div class="field"><label for="qty">Qty label</label><input id="qty" name="qty" type="number" min="1" max="999999" step="1" inputmode="numeric" value="{{ old('qty', 1) }}" required><small>Qty adalah jumlah satuan terpilih. Contoh: 5 BOX akan dihitung sebagai 5 × ratio BOX oleh aplikasi WarehouseCheck.</small></div>
<fieldset class="label-size-fieldset"><legend>Ukuran label</legend><div class="label-size-options"><label class="label-size-option"><input type="radio" name="label_size" value="10x10" @checked(old('label_size','10x10') === '10x10') required>10 × 10 cm</label><label class="label-size-option"><input type="radio" name="label_size" value="4x3" @checked(old('label_size','10x10') === '4x3') required>4 × 3 cm</label></div></fieldset>
<div class="form-summary"><div><span>Nilai QR yang dicetak</span><strong id="qr-preview">-</strong></div><p>QR master berformat BARCODE-QTY. Scanner mengalikan qty dengan ratio satuan; kode barang tanpa barcode memakai KODE-QTY.</p></div>
<button class="button button--primary" id="submit-button" type="submit" disabled>Buat &amp; Preview Label</button><a class="button button--secondary" href="{{ url('/') }}">Kembali ke Stock Opname</a>
</form></section></main>
@endsection
@push('scripts')
<script>window.itemLookupConfig={endpoint:@json(route('label.items.index')),previousCode:@json(old('item_code')),previousBarcodeId:@json(old('barcode_id'))};</script>
<script src="{{ asset('label-assets/js/item-lookup.js') }}?v=20261029unitqty" defer></script>
@endpush
