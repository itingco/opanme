@extends('label.layout')

@section('title', 'Label '.$item->code)

@push('head')
<style>
    @page {
        size: {{ $labelSize === '4x3' ? '50mm 30mm' : '100mm 100mm' }};
        margin: 0;
    }

    /*
     * 4x3 and 10x10 now intentionally use the SAME visual system:
     * QR on the left, item information on the right, audit line below.
     * Only physical dimensions/font scale differ.
     */
    .label-canvas--compact {
        display: grid;
        overflow: hidden;
        box-sizing: border-box;
        border: 0;
        background: #fff;
        color: #000;
        font-family: Arial, Helvetica, sans-serif;
        box-shadow: 0 15px 35px rgba(22, 31, 50, .15);
    }

    .label-canvas--4x3 {
        width: 40mm;
        height: 30mm;
        min-width: 40mm;
        min-height: 30mm;
        grid-template-columns: 18.7mm minmax(0, 1fr);
        grid-template-rows: minmax(0, 1fr) 3.6mm;
    }

    .label-canvas--10x10 {
        width: 100mm;
        height: 100mm;
        min-width: 100mm;
        min-height: 100mm;
        grid-template-columns: 47mm minmax(0, 1fr);
        grid-template-rows: minmax(0, 1fr) 11mm;
    }

    .label-canvas--compact .compact-qr {
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
    }

    .label-canvas--10x10 .compact-qr svg {
        width: 44mm;
        height: 44mm;
    }

    .label-canvas--compact .compact-qr svg {
        display: block;
        max-width: 100%;
        max-height: 100%;
        shape-rendering: crispEdges;
    }

    .label-canvas--compact .compact-details {
        grid-column: 2;
        grid-row: 1;
        min-width: 0;
        min-height: 0;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        justify-content: flex-start;
        overflow: hidden;
    }

    .label-canvas--4x3 .compact-details {
        padding: .65mm .75mm 0 .2mm;
    }

    .label-canvas--10x10 .compact-details {
        padding: 6mm 6mm 0 1mm;
    }

    .label-canvas--compact .compact-name {
        min-width: 0;
        min-height: 0;
        display: block;
        flex: 0 1 auto;
        margin: 0;
        overflow: hidden;
        overflow-wrap: anywhere;
        word-break: normal;
        white-space: normal;
        font-family: Arial, Helvetica, sans-serif;
        font-weight: 700;
        letter-spacing: 0;
    }

    .label-canvas--4x3 .compact-name {
        max-height: 16.5mm;
        padding: .45mm 0 .35mm;
        font-size: 7.5pt;
        line-height: 1.08;
    }

    .label-canvas--10x10 .compact-name {
        max-height: 55mm;
        padding: 2mm 0 1.5mm;
        font-size: 19pt;
        line-height: 1.08;
    }

    .label-canvas--compact .compact-fields {
        min-width: 0;
        min-height: 0;
        display: grid;
        grid-template-columns: minmax(0, 1.25fr) minmax(0, .9fr);
        align-items: center;
    }

    .label-canvas--4x3 .compact-fields {
        flex: 0 0 8.2mm;
        margin-top: 1.3mm;
        padding: .3mm 0;
    }

    .label-canvas--10x10 .compact-fields {
        flex: 0 0 25mm;
        margin-top: 4mm;
        padding: 1mm 0;
    }

    .label-canvas--compact .compact-fields > div {
        min-width: 0;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .label-canvas--4x3 .compact-fields > div + div {
        border-left: .12mm solid #777;
        padding-left: .9mm;
    }

    .label-canvas--10x10 .compact-fields > div + div {
        border-left: .35mm solid #777;
        padding-left: 3mm;
    }

    .label-canvas--compact .compact-field-caption,
    .label-canvas--compact .compact-field-value {
        font-family: Arial, Helvetica, sans-serif;
        font-weight: 700;
        white-space: nowrap;
    }

    .label-canvas--4x3 .compact-field-caption {
        font-size: 4.3pt;
        line-height: 1.15;
    }

    .label-canvas--10x10 .compact-field-caption {
        font-size: 10pt;
        line-height: 1.15;
    }

    .label-canvas--compact .compact-field-value {
        min-width: 0;
        overflow: hidden;
        line-height: 1.1;
    }

    .label-canvas--4x3 .compact-field-value {
        margin-top: .4mm;
        font-size: 9pt;
    }

    .label-canvas--10x10 .compact-field-value {
        margin-top: 1.2mm;
        font-size: 22pt;
    }

    .label-canvas--compact .compact-audit {
        grid-column: 1 / -1;
        grid-row: 2;
        min-width: 0;
        min-height: 0;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        overflow: hidden;
        font-family: Arial, Helvetica, sans-serif;
        font-weight: 400;
        line-height: 1;
        white-space: nowrap;
    }

    .label-canvas--4x3 .compact-audit {
        margin: 0 .9mm;
        border-top: .12mm solid #999;
        font-size: 5.5pt;
    }

    .label-canvas--10x10 .compact-audit {
        margin: 0 5mm;
        border-top: .35mm solid #999;
        font-size: 11pt;
    }

    @media print {
        html,
        body,
        .print-page {
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
            background: #fff !important;
        }

        .print-page {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        @if ($labelSize === '4x3')
        html,
        body,
        .print-page {
            width: 50mm !important;
            min-width: 50mm !important;
            height: 30mm !important;
            min-height: 30mm !important;
        }

        /* Media 50 mm, desain 40 mm: sisa 5 mm di kiri dan kanan. */
        .label-canvas--4x3 {
            width: 40mm !important;
            min-width: 40mm !important;
            max-width: 40mm !important;
            height: 30mm !important;
            min-height: 30mm !important;
            max-height: 30mm !important;
        }
        @else
        html,
        body,
        .print-page,
        .label-canvas--10x10 {
            width: 100mm !important;
            min-width: 100mm !important;
            max-width: 100mm !important;
            height: 100mm !important;
            min-height: 100mm !important;
            max-height: 100mm !important;
        }
        @endif

        .label-canvas--compact {
            margin: 0 !important;
            border: 0 !important;
            box-shadow: none !important;
            break-inside: avoid;
            page-break-inside: avoid;
        }
    }
</style>
@endpush

@section('content')
<div class="print-page">
    <div class="screen-toolbar screen-only">
        <a href="{{ route('label.index') }}" class="button button--secondary">← Kembali</a>
        <button type="button" class="button button--primary" onclick="window.print()">Cetak Label</button>
    </div>

    {{-- 4x3 dan 10x10 sengaja menggunakan markup/design yang sama. --}}
    <article class="label-canvas label-canvas--compact label-canvas--{{ $labelSize }}" aria-label="Label QR {{ $item->code }}">
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

    @if ($labelSize === '4x3')
        <p class="print-hint screen-only">Desain 4 × 3 cm menggunakan media printer LABEL 50 × 30 mm (margin 0). Pilih printer LABEL dengan preset ukuran kertas 50 × 30 mm.</p>
    @else
        <p class="print-hint screen-only">Desain 10 × 10 cm sekarang mengikuti layout 4 × 3: QR di kiri, detail item di kanan. Gunakan skala 100%, margin None/Tidak ada, dan nonaktifkan header/footer.</p>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    function fitCompactLabel() {
        const canvas = document.querySelector('.label-canvas--compact');
        const name = document.getElementById('compact-item-name');
        if (!canvas || !name) return;

        const large = canvas.classList.contains('label-canvas--10x10');

        let nameSize = large ? 19 : 7.5;
        const minNameSize = large ? 10 : 4.5;
        const nameStep = large ? .5 : .25;
        name.style.fontSize = nameSize + 'pt';
        while (name.scrollHeight > name.clientHeight + 1 && nameSize > minNameSize) {
            nameSize -= nameStep;
            name.style.fontSize = nameSize + 'pt';
        }

        const values = Array.from(document.querySelectorAll('.compact-field-value'));
        let valueSize = large ? 22 : 9;
        const minValueSize = large ? 12 : 5;
        const valueStep = large ? .5 : .25;
        values.forEach(value => { value.style.fontSize = valueSize + 'pt'; });
        while (values.some(value => value.scrollWidth > value.clientWidth + 1) && valueSize > minValueSize) {
            valueSize -= valueStep;
            values.forEach(value => { value.style.fontSize = valueSize + 'pt'; });
        }

        const audit = document.querySelector('.compact-audit');
        if (!audit) return;
        let auditSize = large ? 11 : 5.5;
        const minAuditSize = large ? 7 : 4.2;
        const auditStep = large ? .35 : .2;
        audit.style.fontSize = auditSize + 'pt';
        while (audit.scrollWidth > audit.clientWidth + 1 && auditSize > minAuditSize) {
            auditSize -= auditStep;
            audit.style.fontSize = auditSize + 'pt';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fitCompactLabel);
    } else {
        fitCompactLabel();
    }
    window.addEventListener('beforeprint', fitCompactLabel);
})();
</script>
@endpush
