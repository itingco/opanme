@extends('layouts.app')
@section('title','Scan Barcode')
@section('page-class','scanner-page')
@section('content')
<div class="scanner-shell" data-scanner
     data-scan-url="{{ request()->routeIs('warehouse.legacy.*') ? route('warehouse.legacy.scan.store', $session) : route('checker.scan.store', $session) }}"
     data-non-system-url="{{ request()->routeIs('warehouse.legacy.*') ? route('warehouse.legacy.scan.non-system', $session) : route('checker.scan.non-system', $session) }}"
     data-camera-manual-url="{{ request()->routeIs('warehouse.legacy.*') ? route('warehouse.legacy.camera-manual.store', $session) : route('checker.camera-manual.store', $session) }}"
     data-camera-ai-url="{{ request()->routeIs('warehouse.legacy.*') ? route('warehouse.legacy.camera-ai.read', $session) : route('checker.camera-ai.read', $session) }}">
  <div class="scan-context"><div><small>{{ $session->cycle->cycle_no }}</small><strong>{{ $session->warehouse->warehouse_code }}</strong></div><div class="location-chip"><span>Lokasi</span><strong>{{ $session->location }}</strong></div></div>
  <div id="scan-feedback" class="scan-feedback idle"><div class="feedback-icon">⌁</div><div><strong>Siap scan</strong><span>Arahkan kamera ke barcode.</span></div></div>
  <div class="camera-box"><video id="barcode-video" playsinline muted autoplay></video><div class="scan-guide"><span></span></div><button id="start-camera" class="camera-start" type="button">Aktifkan Kamera</button></div>
  <div class="v12-actions">
    <button type="button" id="v12-photo-read" class="btn primary">📷 Ambil Gambar &amp; Baca AI</button>
    <button type="button" id="v12-manual-open" class="btn">✏ Input Kode Barang (Tanpa Barcode)</button>
  </div>
  <p id="v12-camera-status" class="scanner-note" role="status">Foto diambil langsung dari kamera aktif. Tidak memakai galeri atau upload foto.</p>
  <details class="change-location"><summary>Ganti Lokasi / Rak</summary><form method="POST" action="{{ request()->routeIs('warehouse.legacy.*') ? route('warehouse.legacy.session.start', [$session->cycle,$session->warehouse->id]) : route('checker.session.start', [$session->cycle,$session->warehouse->id]) }}" class="location-form compact">@csrf<label>Lokasi baru<input name="location" required maxlength="255"></label><button class="btn full">Pindah Lokasi</button></form></details>
</div>

<div id="v12-manual-modal" class="non-system-modal" hidden>
 <div class="non-system-card" role="dialog" aria-modal="true" aria-labelledby="v12-manual-title">
  <div class="non-system-head"><div><h2 id="v12-manual-title">Barang Tanpa Barcode</h2><p>Isi kode barang dan qty, lalu ambil foto langsung dari kamera. Ketiganya wajib sebelum Simpan.</p></div><button type="button" class="non-system-close" id="v12-manual-close">×</button></div>
  <form id="v12-manual-form" class="non-system-form">
   <label>Kode Barang<input id="v12-manual-code" maxlength="100" required autocomplete="off" placeholder="Contoh: AAC1408"></label>
   <label>Qty (smallest UOM)<input id="v12-manual-qty" type="number" min="0.0001" max="999999999" step="0.0001" required></label>
   <button type="button" id="v12-capture-proof" class="btn">📷 Ambil Foto Bukti dari Kamera</button>
   <img id="v12-proof-preview" alt="Foto bukti dari kamera" hidden style="max-width:100%;max-height:220px;object-fit:contain;border-radius:9px">
   <small id="v12-proof-status">Foto belum diambil.</small>
   <div class="non-system-actions"><button type="button" class="btn" id="v12-manual-cancel">Batal</button><button type="submit" class="btn primary" id="v12-manual-save" disabled>Simpan Qty + Foto</button></div>
  </form>
 </div>
</div>
<div id="v12-ai-modal" class="non-system-modal" hidden>
 <div class="non-system-card" role="dialog" aria-modal="true" aria-labelledby="v12-ai-title">
   <div class="non-system-head"><div><h2 id="v12-ai-title">Konfirmasi Hasil Baca</h2><p>Periksa nilai barcode/QR dari foto sebelum disimpan. AI bisa salah membaca angka dan huruf.</p></div><button class="non-system-close" type="button" id="v12-ai-close">×</button></div>
   <img id="v12-ai-preview" alt="Frame kamera untuk pembacaan barcode" style="max-width:100%;max-height:190px;object-fit:contain;border-radius:9px">
   <label>Barcode / QR yang terbaca<input id="v12-ai-value" maxlength="150" autocomplete="off"></label>
   <small id="v12-ai-status" role="status"></small>
   <div class="non-system-actions"><button type="button" class="btn" id="v12-ai-cancel">Batal</button><button type="button" class="btn primary" id="v12-ai-confirm">Konfirmasi &amp; Proses Scan</button></div>
 </div>
</div>

<div id="non-system-modal" class="non-system-modal" hidden>
 <div class="non-system-card" role="dialog" aria-modal="true" aria-labelledby="non-system-title">
  <div class="non-system-head"><div><span class="non-system-badge">NON-SYSTEM</span><h2 id="non-system-title">Barang tidak ditemukan di ERP</h2><p>Barang ini hanya disimpan dalam cycle aktif.</p></div><button type="button" class="non-system-close" data-non-system-close>×</button></div>
  <form id="non-system-item-form" class="non-system-form">
   <label>Barcode<input id="non-system-barcode" name="barcode" readonly></label>
   <label>Nama Barang<input id="non-system-name" name="item_name" maxlength="255" required><small id="non-system-known-note" hidden>Barang telah dikenal di cycle ini.</small></label>
   <div class="non-system-grid"><label>Qty<input id="non-system-qty" name="qty" type="number" min="0" step="0.0001" value="1" required></label><label>UOM<input id="non-system-uom" name="uom_code" maxlength="50" value="PCS" required></label><label>Isi per UOM<input id="non-system-ratio" name="ratio_to_smallest" type="number" min="0.0001" step="0.0001" value="1" required></label><label>Smallest UOM<input id="non-system-smallest-uom" name="smallest_uom_code" maxlength="50" value="PCS" required></label></div>
   <div class="non-system-conversion" id="non-system-conversion"></div>
   <div class="non-system-actions"><button type="button" class="btn" data-non-system-close>Batal</button><button type="submit" class="btn primary">Simpan Barang Temuan</button></div>
  </form>
 </div>
</div>
@endsection
@push('styles')<style>
.v12-actions{display:flex;flex-wrap:wrap;gap:10px;margin:12px 0}.v12-actions .btn{flex:1;min-width:215px;text-align:center}
#v12-manual-modal[hidden],#v12-ai-modal[hidden],#non-system-modal[hidden]{display:none!important}
</style>@endpush
@push('scripts')<script src="{{ asset('assets/js/checker-camera-v12.js') }}?v=12.1" defer></script>@endpush
