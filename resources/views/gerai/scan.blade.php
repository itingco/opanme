@extends('layouts.app')
@section('title','Scan Sampling')
@section('page-class','sampling-page')
@section('content')
@vite(['resources/css/sampling.css','resources/js/sampling.js'])
<div class="sampling-shell" data-sampling-scanner data-lookup-url="{{ route('gerai.sampling.lookup',$cycle) }}" data-confirm-url="{{ route('gerai.sampling.confirm',$cycle) }}">
    <div class="sampling-head">
        <div><a class="sampling-back" href="{{ route('gerai.sampling.home') }}">← Sampling</a><h1>{{ $cycle->cycle_no }}</h1><p>{{ $cycle->source_database }} · {{ $cycle->warehouse_code }} · {{ $cycle->warehouse_name }}</p></div>
        <div class="sampling-count"><strong id="sample-count">{{ number_format($checkCount) }}</strong><span>item dicek</span></div>
    </div>
    <section class="sampling-location">
        <div><span>Lokasi / Rak aktif</span><strong>{{ $cycle->location }}</strong></div>
        @if($cycle->isOpen())<form method="POST" action="{{ route('gerai.sampling.location',$cycle) }}" class="location-form">@csrf @method('PUT')<input name="location" value="{{ $cycle->location }}" maxlength="255" required><button class="btn" type="submit">Ganti Lokasi</button></form>@endif
    </section>
    @if($cycle->isOpen())
    <section class="sampling-workspace">
        <div class="scan-entry">
            <div class="camera-box"><video id="sample-video" muted playsinline></video><button class="btn" type="button" id="sample-camera">Aktifkan Kamera</button></div>
            <form id="sample-barcode-form"><label>Scan / ketik barcode<input id="sample-barcode" autocomplete="off" inputmode="numeric" placeholder="Scan barcode lalu Enter"></label><button class="btn primary" type="submit">Cek Stok</button></form>
            <div id="sample-feedback" class="sample-feedback idle"><strong>Siap scan</strong><span>Ambil satu barang dari rak lalu scan barcode.</span></div>
        </div>
        <div class="sample-result" id="sample-result" hidden>
            <div class="result-item"><span id="sample-result-code">-</span><h2 id="sample-result-name">-</h2><small id="sample-result-barcode">-</small></div>
            <div class="system-stock"><span>Stok Sistem · Smallest On Hand</span><strong id="sample-system-qty">0</strong><small id="sample-uom">smallest UOM</small></div>
            <div class="result-actions">
                <button type="button" class="sample-match" id="sample-match">✓ Stok Cocok</button>
                <button type="button" class="sample-mismatch" id="sample-mismatch">✕ Tidak Cocok</button>
            </div>
            <form id="sample-mismatch-form" class="mismatch-form" hidden><label>Qty Fisik Sebenarnya<input id="sample-physical-qty" name="physical_qty" type="number" min="0" step="0.0001" placeholder="Masukkan qty fisik"></label><button class="btn primary" type="submit">Simpan Qty Fisik</button><button class="btn" type="button" id="sample-mismatch-cancel">Batal</button></form>
        </div>
    </section>
    @else <div class="alert">Sample cycle sudah CLOSED. Data hanya dapat dilihat.</div> @endif
    <section class="panel sampling-history"><div class="sampling-history-head"><h2>Hasil Sampling Cycle Ini</h2>@if($cycle->isOpen())<form method="POST" action="{{ route('gerai.sampling.close',$cycle) }}" onsubmit="return confirm('Tutup sample cycle ini? Setelah ditutup tidak bisa scan lagi.')">@csrf<button class="btn" type="submit">Tutup Cycle</button></form>@endif</div>
        <div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Lokasi / Rak</th><th>Item</th><th class="num">Sistem</th><th class="num">Fisik</th><th>Hasil</th></tr></thead><tbody id="sample-history-body">@forelse($checks as $check)<tr><td>{{ $check->scanned_at?->format('d/m H:i:s') }}</td><td>{{ $check->location }}</td><td><strong>{{ $check->item_code }}</strong><br><small>{{ $check->item_name }}</small></td><td class="num">{{ number_format((float)$check->system_qty,4,'.',',') }}</td><td class="num">{{ number_format((float)$check->physical_qty,4,'.',',') }}</td><td><span class="sample-status {{ strtolower($check->result) }}">{{ $check->result==='MATCH' ? 'Cocok':'Tidak Cocok' }}</span></td></tr>@empty<tr id="sample-empty-row"><td colspan="6" class="empty">Belum ada item yang disampling.</td></tr>@endforelse</tbody></table></div>
    </section>
</div>
@endsection
