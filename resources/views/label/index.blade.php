@extends('label.layout')
@section('title', 'Cetak Label QR')
@push('head')
<style>
.label-size-options{display:flex;gap:12px;flex-wrap:wrap}.label-size-option{display:flex;gap:8px;align-items:center;padding:12px;border:1px solid #cbd5e1;border-radius:10px;background:#fff}.label-size-fieldset{border:0;padding:0;margin:0}.label-size-fieldset legend{font-weight:700;margin-bottom:8px}
</style>
@endpush
@section('content')
<main class="page-shell"><section class="entry-card" aria-labelledby="page-title">
<div class="entry-card__header"><div><p class="eyebrow">LABEL BARANG</p><h1 id="page-title">Cetak Label QR</h1><p class="subtitle">Pilih barang, isi qty, lalu cetak. QR selalu memakai format KODE_BARANG~QTY.</p></div><div class="label-size-chip">2 Ukuran</div></div>
@if($errors->any())<div class="alert alert--error" role="alert"><strong>Label belum dapat dibuat.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('label.preview') }}" class="label-form" id="label-form" novalidate>@csrf
<div class="field field--lookup"><label for="item_code">Cari nama atau kode barang</label><div class="lookup-wrap"><input id="item_code" name="item_code" type="text" value="{{ old('item_code') }}" maxlength="100" autocomplete="off" placeholder="Ketik nama / kode barang" aria-autocomplete="list" aria-controls="item-results" required><span class="lookup-status" id="lookup-status" aria-live="polite"></span><div class="lookup-results" id="item-results" role="listbox" hidden></div></div><small>Pilih barang dari daftar pencarian.</small></div>
<div class="field"><label for="item_name">Nama barang</label><input id="item_name" type="text" readonly tabindex="-1" placeholder="Muncul otomatis setelah barang dipilih"></div>
<div class="field"><label for="qty">Qty label</label><input id="qty" name="qty" type="number" min="1" max="999999" step="1" inputmode="numeric" value="{{ old('qty', 1) }}" required><small>Qty dibaca langsung dari QR saat scan sampling maupun cycle.</small></div>
<fieldset class="label-size-fieldset"><legend>Ukuran label</legend><div class="label-size-options"><label class="label-size-option"><input type="radio" name="label_size" value="10x10" @checked(old('label_size','10x10') === '10x10') required>10 × 10 cm</label><label class="label-size-option"><input type="radio" name="label_size" value="4x3" @checked(old('label_size','10x10') === '4x3') required>4 × 3 cm</label></div></fieldset>
<div class="form-summary"><div><span>Nilai QR yang dicetak</span><strong id="qr-preview">-</strong></div><p>Contoh: kode barang <strong>00017</strong> dengan qty <strong>1</strong> akan menjadi <strong>00017~1</strong>.</p></div>
<button class="button button--primary" id="submit-button" type="submit" disabled>Buat &amp; Preview Label</button><a class="button button--secondary" href="{{ url('/') }}">Kembali ke Stock Opname</a>
</form></section></main>
@endsection
@push('scripts')
<script>window.itemLookupConfig={endpoint:@json(route('label.items.index')),previousCode:@json(old('item_code'))};</script>
<script src="{{ asset('label-assets/js/item-lookup.js') }}?v=20261102simpletilde" defer></script>
@endpush
