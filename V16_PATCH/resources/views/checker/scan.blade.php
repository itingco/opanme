@extends('layouts.app')
@section('title', 'Scan Checker')
@section('page-class', 'scanner-page')
@section('content')
@php($legacy = request()->routeIs('warehouse.legacy.*'))
<div class="scanner-shell" id="v15-checker"
     data-item-lookup-url="{{ $legacy ? route('warehouse.legacy.manual-item.lookup', $session) : route('checker.manual-item.lookup', $session) }}"
     data-camera-manual-url="{{ $legacy ? route('warehouse.legacy.camera-manual.store', $session) : route('checker.camera-manual.store', $session) }}"
     data-camera-ai-url="{{ $legacy ? route('warehouse.legacy.camera-ai.read', $session) : route('checker.camera-ai.read', $session) }}"
     data-scan-url="{{ $legacy ? route('warehouse.legacy.scan.store', $session) : route('checker.scan.store', $session) }}"
     data-non-system-url="{{ $legacy ? route('warehouse.legacy.scan.non-system', $session) : route('checker.scan.non-system', $session) }}">
    <div class="scan-context">
        <div><small>{{ $session->cycle->cycle_no }}</small><strong>{{ $session->warehouse->warehouse_code }}</strong></div>
        <div class="location-chip"><span>Lokasi</span><strong>{{ $session->location }}</strong></div>
    </div>
    <div id="v15-feedback" class="scan-feedback idle" role="status" aria-live="polite">
        <div class="feedback-icon">✓</div><div><strong>Siap input barang</strong><span>Masukkan kode barang yang akan dihitung.</span></div>
    </div>
    <section class="v16-camera-panel" aria-label="Scan barcode otomatis">
        <div class="v16-header"><div><h2>Scan Barcode dengan Kamera</h2><p>Arahkan barcode ke kamera. Scanner membaca otomatis; jika belum berhasil, tekan Ambil Gambar &amp; Baca AI.</p></div><span class="v16-live" id="v16-camera-indicator">Nonaktif</span></div>
        <div class="v16-video-wrap"><video id="v16-scanner-video" playsinline muted autoplay></video><div class="v16-guides" aria-hidden="true"></div><span class="v16-video-text" id="v16-video-text">Tekan Aktifkan Kamera untuk mulai scan</span></div>
        <div class="v16-camera-actions"><button class="btn primary" id="v16-camera-start" type="button">Aktifkan Kamera</button><button class="btn" id="v16-camera-stop" type="button" disabled>Matikan Kamera</button><button class="btn v16-photo-btn" id="v16-camera-read" type="button" disabled>📷 Ambil Gambar &amp; Baca AI</button></div>
        <p id="v16-camera-status" class="v16-status" role="status" aria-live="polite">Kamera belum aktif.</p>
        <div id="v16-confirm" class="v16-confirm" hidden><label for="v16-ai-code">Hasil pembacaan barcode (periksa sebelum menyimpan)</label><div class="v16-confirm-row"><input id="v16-ai-code" maxlength="150" autocomplete="off" placeholder="Kode barcode / QR KODE~QTY"><button class="btn primary" id="v16-confirm-scan" type="button">Konfirmasi &amp; Proses</button><button class="btn" id="v16-cancel-read" type="button">Batal</button></div></div>
    </section>
    <section class="v15-panel">
        <h2>Input Kode Barang (Tanpa Barcode)</h2>
        <p>Untuk barang tanpa barcode: masukkan kode barang, klik Cek Barang, lalu isi Qty dan foto bukti dari kamera pada modal.</p>
        <form id="v15-lookup-form" class="v15-code-row">
            <label for="v15-code">Kode Barang</label>
            <div class="v15-inline"><input id="v15-code" name="item_code" autocomplete="off" autocapitalize="characters" maxlength="100" placeholder="Contoh: AAC1408" required><button id="v15-check" type="submit" class="btn primary">Cek Barang</button></div>
        </form>
        <p id="v15-lookup-result" role="status"></p>
    </section>
    <details class="change-location"><summary>Ganti Lokasi / Rak</summary>
        <form method="POST" action="{{ $legacy ? route('warehouse.legacy.session.start', [$session->cycle, $session->warehouse->id]) : route('checker.session.start', [$session->cycle, $session->warehouse->id]) }}" class="location-form compact">
            @csrf<label>Lokasi baru<input name="location" required maxlength="255"></label><button class="btn full">Pindah Lokasi</button>
        </form>
    </details>
</div>
<div id="v15-manual-modal" class="non-system-modal" hidden>
    <section class="non-system-card v15-dialog" role="dialog" aria-modal="true" aria-labelledby="v15-modal-title">
        <div class="non-system-head"><div><span class="v15-eyebrow">CHECKER • INPUT MANUAL</span><h2 id="v15-modal-title">Hitung Barang & Foto Bukti</h2><p>Pastikan jumlah dan foto sesuai barang fisik.</p></div><button type="button" class="non-system-close" id="v15-close" aria-label="Tutup">×</button></div>
        <form id="v15-save-form" class="non-system-form">
            <div class="v15-item-card"><strong id="v15-item-code"></strong><span id="v15-item-name"></span><small id="v15-item-database"></small></div>
            <label for="v15-qty">Qty Fisik <small>(smallest UOM)</small><input id="v15-qty" type="number" min="0.0001" max="999999999" step="0.0001" required placeholder="Masukkan jumlah barang"></label>
            <div class="v15-photo-area">
                <div class="v15-section-head"><strong>Foto Barang <span class="v15-required">Wajib</span></strong><small>Langsung dari kamera, tanpa galeri</small></div>
                <div class="v15-video-wrap"><video id="v15-video" playsinline autoplay muted></video><span id="v15-video-hint">Kamera belum aktif</span></div>
                <div class="v15-camera-buttons"><button type="button" id="v15-start-camera" class="btn">Aktifkan Kamera</button><button type="button" id="v15-capture" class="btn primary" disabled>Ambil Foto</button><button type="button" id="v15-retake" class="btn" hidden>Foto Ulang</button></div>
                <p id="v15-camera-status" role="status">Tekan Aktifkan Kamera, arahkan kamera ke barang, lalu ambil foto.</p>
                <img id="v15-preview" alt="Foto bukti yang baru diambil" hidden>
            </div>
            <p id="v15-save-status" role="status"></p>
            <div class="non-system-actions"><button type="button" class="btn" id="v15-cancel">Batal</button><button type="submit" class="btn primary" id="v15-save" disabled>Simpan Qty + Foto</button></div>
        </form>
        <details class="v15-ai-optional"><summary>Barcode tidak terbaca? Ambil frame dan Baca dengan AI</summary><p>Gunakan kamera yang sama. Hasil AI wajib dikonfirmasi sebelum diproses sebagai scan barcode.</p><button type="button" id="v15-ai-read" class="btn">Ambil Gambar & Baca AI</button><p id="v15-ai-status" role="status"></p><label>Hasil Kode / Barcode<input id="v15-ai-value" maxlength="150" placeholder="Hasil pembacaan"></label><button type="button" id="v15-ai-confirm" class="btn" disabled>Konfirmasi dan Scan Barcode</button></details>
    </section>
</div>
<div id="v15-non-system-modal" class="non-system-modal" hidden>
 <section class="non-system-card v15-dialog" role="dialog" aria-modal="true" aria-labelledby="v15-ns-title"><div class="non-system-head"><div><h2 id="v15-ns-title">Barang Non-System</h2><p>Barcode belum dikenali pada cycle ini. Lengkapi data temuan untuk mencatatnya.</p></div><button type="button" class="non-system-close" id="v15-ns-close">×</button></div>
 <form id="v15-ns-form" class="non-system-form"><label>Barcode<input id="v15-ns-barcode" readonly required></label><label>Nama Barang<input id="v15-ns-name" maxlength="255" required></label><div class="non-system-grid"><label>Qty<input id="v15-ns-qty" type="number" step="0.0001" min="0.0001" value="1" required></label><label>UOM<input id="v15-ns-uom" maxlength="50" value="PCS" required></label><label>Isi per UOM<input id="v15-ns-ratio" type="number" min="0.0001" step="0.0001" value="1" required></label><label>Smallest UOM<input id="v15-ns-smallest" maxlength="50" value="PCS" required></label></div><p id="v15-ns-status" role="status"></p><div class="non-system-actions"><button type="button" class="btn" id="v15-ns-cancel">Batal</button><button type="submit" class="btn primary">Simpan Barang Temuan</button></div></form>
 </section>
</div>
@endsection
@push('styles')
<style>
#v15-manual-modal[hidden],#v15-non-system-modal[hidden],#v15-preview[hidden]{display:none!important}
.v15-panel{background:var(--surface,#fff);border:1px solid #d9e2ef;border-radius:16px;padding:20px;margin:14px 0}.v15-panel h2{margin:0 0 5px;font-size:18px}.v15-panel p{color:#5a6a83;font-size:13px}.v15-code-row{margin-top:14px}.v15-code-row>label{font-weight:700;display:block;margin-bottom:8px}.v15-inline{display:flex;gap:8px}.v15-inline input{flex:1;min-width:0;padding:13px;border:1px solid #ccd6e4;border-radius:9px}.v15-dialog{width:min(94vw,640px);max-height:92vh;overflow:auto}.v15-eyebrow{font-size:10px;letter-spacing:1px;color:#64748b}.v15-item-card{display:grid;gap:4px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px;color:#0f2745}.v15-item-card strong{font-size:18px}.v15-item-card small{color:#64748b}.v15-photo-area{border:1px solid #d9e2ef;border-radius:12px;padding:12px;display:grid;gap:10px}.v15-section-head{display:flex;justify-content:space-between;gap:7px;flex-wrap:wrap}.v15-section-head small{color:#64748b}.v15-required{font-size:11px;background:#fef2f2;color:#b91c1c;border-radius:20px;padding:2px 8px}.v15-video-wrap{position:relative;background:#0f172a;border-radius:12px;overflow:hidden;min-height:160px}.v15-video-wrap video{display:block;width:100%;max-height:300px;object-fit:cover}.v15-video-wrap span{position:absolute;bottom:10px;left:10px;color:#fff;background:#0008;padding:5px 9px;border-radius:8px;font-size:12px}.v15-camera-buttons{display:flex;gap:8px;flex-wrap:wrap}.v15-camera-buttons .btn{flex:1;min-width:100px}.v15-photo-area img{width:100%;max-height:260px;object-fit:contain;border-radius:9px;background:#f1f5f9}.v15-ai-optional{border-top:1px solid #e2e8f0;margin-top:16px;padding-top:12px}.v15-ai-optional summary{cursor:pointer;font-weight:700;color:#1d4ed8}.v15-ai-optional p{font-size:12px;color:#64748b}#v15-save-status,#v15-camera-status,#v15-lookup-result{font-size:13px}#v15-save-status:not(:empty),#v15-lookup-result:not(:empty){padding:8px 0}.v15-dialog .non-system-form>label{display:grid;gap:6px}
@media(max-width:560px){.v15-inline{flex-direction:column}.v15-dialog{width:98vw}.v15-panel{padding:14px}}

#v16-confirm[hidden]{display:none!important}
.v16-camera-panel{background:#fff;border:1px solid #d9e2ef;border-radius:16px;padding:18px;margin:14px 0}
.v16-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.v16-header h2{font-size:18px;margin:0 0 5px}.v16-header p{font-size:13px;color:#64748b;margin:0 0 12px}.v16-live{background:#e2e8f0;color:#334155;font-size:12px;font-weight:700;border-radius:30px;padding:5px 10px;white-space:nowrap}.v16-live.active{background:#dcfce7;color:#166534}
.v16-video-wrap{position:relative;overflow:hidden;background:#111827;border-radius:13px;min-height:220px;aspect-ratio:4/3;display:flex;align-items:center;justify-content:center}.v16-video-wrap video{width:100%;height:100%;position:absolute;inset:0;object-fit:cover}.v16-guides{pointer-events:none;border:2px solid rgba(255,255,255,.85);border-radius:12px;width:76%;height:37%;position:absolute;z-index:2}.v16-video-text{position:absolute;bottom:13px;left:13px;background:#000a;color:#fff;padding:7px 10px;border-radius:8px;z-index:3;font-size:12px}
.v16-camera-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.v16-camera-actions .btn{flex:1;white-space:normal}.v16-photo-btn{background:#0f766e!important;color:#fff!important}.v16-status{font-size:13px;color:#475569;min-height:18px;margin:10px 0 0}.v16-confirm{border:1px solid #93c5fd;background:#eff6ff;padding:13px;border-radius:12px;margin-top:12px}.v16-confirm label{font-weight:700;display:block;margin-bottom:8px}.v16-confirm-row{display:flex;gap:7px;flex-wrap:wrap}.v16-confirm-row input{flex:1;min-width:160px;border:1px solid #94a3b8;border-radius:8px;padding:10px}
@media(max-width:550px){.v16-camera-actions{flex-direction:column}.v16-camera-panel{padding:12px}.v16-header{display:block}.v16-live{display:inline-block;margin-bottom:8px}}
</style>
@endpush
@push('scripts')
<script src="{{ asset('assets/js/checker-manual-v15.js') }}?v=16.0" defer></script>
<script src="{{ asset('assets/js/checker-camera-v16.js') }}?v=16.0" defer></script>
@endpush
