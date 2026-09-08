@extends('layouts.app')
@section('title','Scan Sampling')
@section('page-class','sampling-scanner-page')
@section('content')
@vite(['resources/css/sampling.css','resources/js/sampling.js'])

<div class="sampling-shell"
     data-sampling-scanner
     data-lookup-url="{{ route('gerai.sampling.lookup',$cycle) }}"
     data-confirm-url="{{ route('gerai.sampling.confirm',$cycle) }}">

    <div class="sampling-nav-row">
        <a class="sampling-back" href="{{ route('gerai.sampling.home') }}">← Sampling</a>
        <span class="sampling-count-inline"><strong id="sample-count">{{ number_format($checkCount) }}</strong> item dicek</span>
    </div>

    <div class="scan-context sampling-context">
        <div>
            <small>{{ $cycle->cycle_no }} · {{ $cycle->source_database }}</small>
            <strong>{{ $cycle->warehouse_code }}</strong>
            <span>{{ $cycle->warehouse_name }}</span>
        </div>
        <div class="location-chip">
            <span>Lokasi</span>
            <strong>{{ $cycle->location }}</strong>
        </div>
    </div>

    @if($cycle->isOpen())
        <div id="sample-feedback" class="scan-feedback sampling-scan-feedback idle">
            <div class="feedback-icon">⌁</div>
            <div>
                <strong>Siap scan</strong>
                <span>Arahkan kamera ke barcode barang.</span>
            </div>
        </div>

        <div class="camera-box sampling-camera-box">
            <video id="sample-video" muted playsinline></video>
            <div class="scan-guide"><span></span></div>
            <button class="camera-start" type="button" id="sample-camera">Aktifkan Kamera</button>
        </div>

        <form id="sample-barcode-form" class="manual-scan sampling-manual-scan">
            <label>
                Scanner / input barcode
                <input id="sample-barcode" autocomplete="off" inputmode="numeric" placeholder="Scan atau ketik barcode">
            </label>
            <button class="btn primary" type="submit">Proses</button>
        </form>

        <section class="sample-result sampling-result-card" id="sample-result" hidden>
            <div class="result-item">
                <span id="sample-result-code">-</span>
                <h2 id="sample-result-name">-</h2>
                <small id="sample-result-barcode">-</small>
            </div>

            <div class="system-stock">
                <span>STOK SISTEM · SMALLEST ON HAND</span>
                <strong id="sample-system-qty">0</strong>
                <small id="sample-uom">smallest UOM</small>
            </div>

            <div class="result-actions">
                <button type="button" class="sample-match" id="sample-match">✓ Stok Cocok</button>
                <button type="button" class="sample-mismatch" id="sample-mismatch">✕ Tidak Cocok</button>
            </div>

            <form id="sample-mismatch-form" class="mismatch-form" hidden>
                <label>
                    Qty Fisik Sebenarnya
                    <input id="sample-physical-qty" name="physical_qty" type="number" min="0" step="0.0001" inputmode="decimal" placeholder="Masukkan qty fisik">
                </label>
                <button class="btn primary" type="submit">Simpan Qty Fisik</button>
                <button class="btn" type="button" id="sample-mismatch-cancel">Batal</button>
            </form>
        </section>

        <details class="change-location sampling-change-location">
            <summary>Ganti Lokasi / Rak</summary>
            <form method="POST" action="{{ route('gerai.sampling.location',$cycle) }}" class="location-form compact">
                @csrf
                @method('PUT')
                <label>
                    Lokasi baru
                    <input name="location" value="{{ $cycle->location }}" maxlength="255" required autocomplete="off" placeholder="Contoh: RAK A-02">
                </label>
                <button class="btn full" type="submit">Pindah Lokasi</button>
            </form>
        </details>
    @else
        <div class="alert">Sample cycle sudah CLOSED. Data hanya dapat dilihat.</div>
    @endif

    <details class="panel sampling-history-details" open>
        <summary class="sampling-history-summary">
            <span>
                <strong>Riwayat Sampling</strong>
                <small>{{ number_format($checkCount) }} item pada cycle ini</small>
            </span>
            <span class="sampling-history-chevron">⌄</span>
        </summary>

        <div class="sampling-history-content">
            @if($cycle->isOpen())
                <div class="sampling-history-actions">
                    <form method="POST" action="{{ route('gerai.sampling.close',$cycle) }}" onsubmit="return confirm('Tutup sample cycle ini? Setelah ditutup tidak bisa scan lagi.')">
                        @csrf
                        <button class="btn" type="submit">Tutup Cycle</button>
                    </form>
                </div>
            @endif

            <div class="table-wrap sampling-history-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Lokasi / Rak</th>
                            <th>Item</th>
                            <th class="num">Sistem</th>
                            <th class="num">Fisik</th>
                            <th>Hasil</th>
                        </tr>
                    </thead>
                    <tbody id="sample-history-body">
                        @forelse($checks as $check)
                            <tr>
                                <td>{{ $check->scanned_at?->format('d/m H:i:s') }}</td>
                                <td>{{ $check->location }}</td>
                                <td><strong>{{ $check->item_code }}</strong><br><small>{{ $check->item_name }}</small></td>
                                <td class="num">{{ number_format((float)$check->system_qty,4,'.',',') }}</td>
                                <td class="num">{{ number_format((float)$check->physical_qty,4,'.',',') }}</td>
                                <td><span class="sample-status {{ strtolower($check->result) }}">{{ $check->result==='MATCH' ? 'Cocok':'Tidak Cocok' }}</span></td>
                            </tr>
                        @empty
                            <tr id="sample-empty-row"><td colspan="6" class="empty">Belum ada item yang disampling.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </details>
</div>
@endsection
