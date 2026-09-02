@extends('layouts.app')
@section('title','Scan Barcode')
@section('page-class','scanner-page')
@section('content')
<div class="scanner-shell"
     data-scanner
     data-scan-url="{{ route('checker.scan.store',$session) }}"
     data-non-system-url="{{ route('checker.scan.non-system',$session) }}">
    <div class="scan-context"><div><small>{{ $session->cycle->cycle_no }}</small><strong>{{ $session->warehouse->warehouse_code }}</strong></div><div class="location-chip"><span>Lokasi</span><strong>{{ $session->location }}</strong></div></div>
    <div id="scan-feedback" class="scan-feedback idle"><div class="feedback-icon">⌁</div><div><strong>Siap scan</strong><span>Arahkan kamera ke barcode barang.</span></div></div>
    <div class="camera-box"><video id="barcode-video" playsinline muted></video><div class="scan-guide"><span></span></div><button id="start-camera" class="camera-start" type="button">Aktifkan Kamera</button></div>
    <form id="manual-scan-form" class="manual-scan"><label>Scanner / input barcode<input id="manual-barcode" autocomplete="off" placeholder="Scan atau ketik barcode"></label><button class="btn primary" type="submit">Proses</button></form>
    <details class="change-location"><summary>Ganti Lokasi / Rak</summary><form method="POST" action="{{ route('checker.session.start',[$session->cycle,$session->warehouse->id]) }}" class="location-form compact">@csrf<label>Lokasi baru<input name="location" required maxlength="255" placeholder="Contoh: RAK A-02" autocomplete="off"></label><button class="btn full" type="submit">Pindah Lokasi</button></form></details>
    <p class="scanner-note">Checker tidak menampilkan stok sistem, progress, ratio ERP, atau variance. Barang yang tidak ada di ERP dapat dicatat sebagai Non-System.</p>
</div>

<div id="non-system-modal" class="non-system-modal" hidden>
    <div class="non-system-card" role="dialog" aria-modal="true" aria-labelledby="non-system-title">
        <div class="non-system-head">
            <div>
                <span class="non-system-badge">NON-SYSTEM</span>
                <h2 id="non-system-title">Barang tidak ditemukan di ERP</h2>
                <p>Data ini hanya disimpan pada cycle stock opname dan tidak membuat master item ERP.</p>
            </div>
            <button type="button" class="non-system-close" data-non-system-close aria-label="Tutup">×</button>
        </div>

        <form id="non-system-item-form" class="non-system-form">
            <label>
                Barcode
                <input id="non-system-barcode" name="barcode" readonly>
            </label>
            <label id="non-system-name-field">
                Nama Barang
                <input id="non-system-name" name="item_name" maxlength="255" placeholder="Contoh: Tang Kombinasi tanpa master" required>
                <small id="non-system-known-note" hidden>Nama barang sudah dikenal dari scan Non-System sebelumnya.</small>
            </label>

            <div class="non-system-grid">
                <label>
                    Qty
                    <input id="non-system-qty" name="qty" type="number" min="0" step="0.0001" value="1" required>
                </label>
                <label>
                    UOM
                    <input id="non-system-uom" name="uom_code" list="non-system-uom-list" maxlength="50" value="PCS" required>
                </label>
                <label>
                    Isi per UOM
                    <input id="non-system-ratio" name="ratio_to_smallest" type="number" min="0.0001" step="0.0001" value="1" required>
                </label>
                <label>
                    Smallest UOM
                    <input id="non-system-smallest-uom" name="smallest_uom_code" list="non-system-uom-list" maxlength="50" value="PCS" required>
                </label>
            </div>

            <datalist id="non-system-uom-list">
                <option value="PCS"><option value="UNIT"><option value="SET"><option value="BOX"><option value="KRTN"><option value="PACK"><option value="PAK"><option value="ROLL"><option value="MTR"><option value="KG">
            </datalist>

            <div class="non-system-conversion" id="non-system-conversion">1 PCS × 1 = 1 PCS</div>
            <div class="non-system-actions">
                <button type="button" class="btn" data-non-system-close>Batal</button>
                <button type="submit" class="btn primary">Simpan Barang Temuan</button>
            </div>
        </form>
    </div>
</div>
@endsection
