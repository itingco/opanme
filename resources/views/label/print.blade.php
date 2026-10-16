@extends('label.layout')

@section('title', 'Label '.$item->code)

@push('head')
<style>
    /* Label 4 x 3 cm dicetak pada media printer LABEL 50 x 30 mm.
       Ukuran lain (10 x 10 cm) tetap memakai style existing. */
    @page {
        size: {{ $labelSize === '4x3' ? '50mm 30mm' : '10cm 10cm' }};
        margin: 0;
    }

    @if ($labelSize === '4x3')
    .label-canvas--4x3 {
        width: 40mm;
        height: 30mm;
        min-width: 40mm;
        min-height: 30mm;
        display: grid;
        grid-template-columns: 18.7mm minmax(0, 1fr);
        grid-template-rows: minmax(0, 1fr) 3.6mm;
        overflow: hidden;
        box-sizing: border-box;
        border: 0;
        background: #fff;
        color: #000;
        font-family: Arial, Helvetica, sans-serif;
        box-shadow: 0 15px 35px rgba(22, 31, 50, .15);
    }

    .label-canvas--4x3 .compact-qr {
        grid-column: 1;
        grid-row: 1;
        min-width: 0;
        min-height: 0;
        display: grid;
        place-items: center;
        padding: 0;
    }

    .label-canvas--4x3 .compact-qr svg {
        width: 18mm;
        height: 18mm;
        display: block;
        max-width: 100%;
        max-height: 100%;
        shape-rendering: crispEdges;
    }

    .label-canvas--4x3 .compact-details {
        grid-column: 2;
        grid-row: 1;
        min-width: 0;
        min-height: 0;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        justify-content: flex-start;
        padding: .65mm .75mm 0 .2mm;
        overflow: hidden;
    }

    .label-canvas--4x3 .compact-name {
        min-width: 0;
        min-height: 0;
        display: block;
        flex: 0 1 auto;
        max-height: 16.5mm;
        margin: 0;
        padding: .45mm 0 .35mm;
        overflow: hidden;
        overflow-wrap: anywhere;
        word-break: normal;
        white-space: normal;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 7.5pt;
        font-weight: 700;
        line-height: 1.08;
        letter-spacing: 0;
    }

    .label-canvas--4x3 .compact-fields {
        min-width: 0;
        min-height: 0;
        display: grid;
        flex: 0 0 8.2mm;
        margin-top: 1.3mm;
        grid-template-columns: minmax(0, 1.25fr) minmax(0, .9fr);
        align-items: center;
        padding: .3mm 0;
    }

    .label-canvas--4x3 .compact-fields > div {
        min-width: 0;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .label-canvas--4x3 .compact-fields > div + div {
        border-left: .12mm solid #777;
        padding-left: .9mm;
    }

    .label-canvas--4x3 .compact-field-caption {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 4.3pt;
        font-weight: 700;
        line-height: 1.15;
        white-space: nowrap;
    }

    .label-canvas--4x3 .compact-field-value {
        min-width: 0;
        margin-top: .4mm;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 9pt; /* Sama persis antara kode barang dan QTY. */
        font-weight: 700;
        line-height: 1.1;
        white-space: nowrap;
        overflow: hidden;
    }

    .label-canvas--4x3 .compact-audit {
        grid-column: 1 / -1;
        grid-row: 2;
        min-width: 0;
        min-height: 0;
        margin: 0 .9mm;
        border-top: .12mm solid #999;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        overflow: hidden;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 5.5pt;
        font-weight: 400;
        line-height: 1;
        white-space: nowrap;
    }

    @media print {
        @page {
            size: 50mm 30mm;
            margin: 0;
        }

        html,
        body {
            width: 50mm !important;
            min-width: 50mm !important;
            height: 30mm !important;
            min-height: 30mm !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
            background: #fff !important;
        }

        .print-page {
            width: 50mm !important;
            min-width: 50mm !important;
            height: 30mm !important;
            min-height: 30mm !important;
            margin: 0 !important;
            padding: 0 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            overflow: hidden !important;
            background: #fff !important;
        }

        /* Media 50 mm lebar, desain 40 mm: sisa 5 mm di kiri dan kanan. */
        .label-canvas--4x3 {
            width: 40mm !important;
            min-width: 40mm !important;
            max-width: 40mm !important;
            height: 30mm !important;
            min-height: 30mm !important;
            max-height: 30mm !important;
            margin: 0 !important;
            border: 0 !important;
            box-shadow: none !important;
            break-inside: avoid;
            page-break-inside: avoid;
        }
    }
    @endif
</style>
@endpush

@section('content')
<div class="print-page">
    <div class="screen-toolbar screen-only">
        <a href="{{ route('label.index') }}" class="button button--secondary">← Kembali</a>
        <button type="button" class="button button--primary" onclick="window.print()">Cetak Label</button>
    </div>

    @if ($labelSize === '4x3')
        {{-- Tata letak baru HANYA untuk label 4 x 3 cm. Isi QR tetap sama. --}}
        <article class="label-canvas label-canvas--4x3" aria-label="Label QR {{ $item->code }}">
            <div class="compact-qr" aria-label="QR berisi {{ $qrValue }}">
                {!! SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                    ->size(520)
                    ->margin(1)
                    ->errorCorrection('H')
                    ->generate($qrValue) !!}
            </div>

            <div class="compact-details">
                <div class="compact-name" id="compact-item-name">{{ $item->name }}</div>
                <div class="compact-fields">
                    <div>
                        <span class="compact-field-caption">KODE BARANG</span>
                        <strong class="compact-field-value">{{ $item->code }}</strong>
                    </div>
                    <div>
                        <span class="compact-field-caption">QTY</span>
                        <strong class="compact-field-value">{{ $qty }}</strong>
                    </div>
                </div>
            </div>

            <div class="compact-audit">{{ $printedBy }}, {{ $printedAt->format('d-m-Y H:i:s') }}</div>
        </article>
    @else
        {{-- Format standar 10 x 10 cm tidak diubah. --}}
        <article class="label-canvas" aria-label="Label QR {{ $item->code }}">
            <header class="label-header">
                <span>ITEM LABEL</span>
                <strong>{{ $item->code }}</strong>
            </header>

            <div class="qr-box" aria-label="QR berisi {{ $qrValue }}">
                {!! SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                    ->size(520)
                    ->margin(1)
                    ->errorCorrection('H')
                    ->generate($qrValue) !!}
            </div>

            <div class="label-information">
                <h1>{{ $item->name }}</h1>
                <div class="label-meta">
                    <div>
                        <span>Kode barang</span>
                        <strong>{{ $item->code }}</strong>
                    </div>
                    <div class="qty-block">
                        <span>QTY</span>
                        <strong>{{ $qty }}</strong>
                    </div>
                </div>
                <p class="qr-value">QR: {{ $qrValue }}</p>

                <div class="print-audit">
                    <div>
                        <span>Dicetak</span>
                        <strong>{{ $printedAt->format('d/m/Y H:i:s') }}</strong>
                    </div>
                    <div>
                        <span>User</span>
                        <strong>{{ $printedBy }}</strong>
                    </div>
                </div>
            </div>
        </article>
    @endif

    @if ($labelSize === '4x3')
        <p class="print-hint screen-only">Desain 4 × 3 cm menggunakan media printer LABEL 50 × 30 mm (margin 0). Pilih printer LABEL dengan preset ukuran kertas 50 × 30 mm.</p>
    @else
        <p class="print-hint screen-only">Ukuran: 10 × 10 cm. Gunakan skala 100%, margin “None/Tidak ada”, dan nonaktifkan header/footer pada dialog print. Pastikan ukuran kertas printer sesuai.</p>
    @endif
</div>
@endsection

@if ($labelSize === '4x3')
@push('scripts')
<script>
    // Nama barang panjang diperkecil bertahap agar lebih banyak teks yang muat,
    // tanpa elipsis. Berjalan pada preview dan saat dialog cetak dibuka.
    (function () {
        function fitCompactName() {
            const name = document.getElementById('compact-item-name');
            if (!name) return;
            let nameSize = 7.5;
            name.style.fontSize = nameSize + 'pt';
            while (name.scrollHeight > name.clientHeight + 1 && nameSize > 4.5) {
                nameSize -= 0.25;
                name.style.fontSize = nameSize + 'pt';
            }

            // Kode barang dan QTY selalu memakai ukuran font yang sama,
            // termasuk jika angkanya panjang.
            const values = Array.from(document.querySelectorAll('.compact-field-value'));
            let valueSize = 9;
            values.forEach((value) => { value.style.fontSize = valueSize + 'pt'; });
            while (values.some((value) => value.scrollWidth > value.clientWidth + 1) && valueSize > 5) {
                valueSize -= .25;
                values.forEach((value) => { value.style.fontSize = valueSize + 'pt'; });
            }

            const audit = document.querySelector('.compact-audit');
            if (!audit) return;
            let auditSize = 5.5;
            audit.style.fontSize = auditSize + 'pt';
            while (audit.scrollWidth > audit.clientWidth + 1 && auditSize > 4.2) {
                auditSize -= .2;
                audit.style.fontSize = auditSize + 'pt';
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fitCompactName);
        } else {
            fitCompactName();
        }
        window.addEventListener('beforeprint', fitCompactName);
    })();
</script>
@endpush
@endif
