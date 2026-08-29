@extends('layouts.app')
@section('title','Scan Barcode')
@section('page-class','scanner-page')
@section('content')
<div class="scanner-shell" data-scanner data-scan-url="{{ route('checker.scan.store',$session) }}">
    <div class="scan-context"><div><small>{{ $session->cycle->cycle_no }}</small><strong>{{ $session->warehouse->warehouse_code }}</strong></div><div class="location-chip"><span>Lokasi</span><strong>{{ $session->location }}</strong></div></div>
    <div id="scan-feedback" class="scan-feedback idle"><div class="feedback-icon">⌁</div><div><strong>Siap scan</strong><span>Arahkan kamera ke barcode barang.</span></div></div>
    <div class="camera-box"><video id="barcode-video" playsinline muted></video><div class="scan-guide"><span></span></div><button id="start-camera" class="camera-start" type="button">Aktifkan Kamera</button></div>
    <form id="manual-scan-form" class="manual-scan"><label>Scanner / input barcode<input id="manual-barcode" autocomplete="off" placeholder="Scan atau ketik barcode"></label><button class="btn primary" type="submit">Proses</button></form>
    <details class="change-location"><summary>Ganti Lokasi / Rak</summary><form method="POST" action="{{ route('checker.session.start',[$session->cycle,$session->warehouse->id]) }}" class="location-form compact">@csrf<label>Lokasi baru<input name="location" required maxlength="255" placeholder="Contoh: RAK A-02" autocomplete="off"></label><button class="btn full" type="submit">Pindah Lokasi</button></form></details>
    <p class="scanner-note">Checker tidak menampilkan stok sistem, progress, ratio, atau variance. Kamera web production memerlukan HTTPS.</p>
</div>
@endsection
