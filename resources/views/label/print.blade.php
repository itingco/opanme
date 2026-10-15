@extends('label.layout')

@section('title', 'Label '.$item->code)

@push('head')
<style>
    @page {
        size: {{ $labelSize === '4x3' ? '4cm 3cm' : '10cm 10cm' }};
        margin: 0;
    }

    @if ($labelSize === '4x3')
    /* Label kecil LANDSCAPE (lebar 4 cm × tinggi 3 cm). 10x10 tetap memakai style lama. */
    .label-canvas--4x3 {
        width: 4cm;
        height: 3cm;
        display: grid;
        grid-template-columns: 2.13cm minmax(0, 1fr);
        grid-template-rows: .36cm minmax(0, 1fr);
    }

    .label-canvas--4x3 .label-header {
        grid-column: 1 / -1;
        min-width: 0;
        padding: .025cm .10cm;
        font-size: .13cm;
        letter-spacing: 0;
    }

    .label-canvas--4x3 .label-header strong {
        min-width: 0;
        overflow: hidden;
        font-size: .17cm;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .label-canvas--4x3 .qr-box {
        min-width: 0;
        min-height: 0;
        border-right: .03cm solid #111827;
        border-bottom: 0;
        padding: .08cm;
    }

    .label-canvas--4x3 .qr-box svg {
        width: 1.89cm;
        height: 1.89cm;
    }

    .label-canvas--4x3 .label-information {
        min-width: 0;
        min-height: 0;
        padding: 0 .07cm .04cm;
    }

    .label-canvas--4x3 .label-information h1 {
        flex: 0 0 .59cm;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        overflow: hidden;
        padding: .05cm 0;
        white-space: normal;
        overflow-wrap: anywhere;
        font-size: .17cm;
        line-height: 1.12;
        letter-spacing: 0;
    }

    .label-canvas--4x3 .label-meta {
        flex: 0 0 .59cm;
        grid-template-columns: minmax(0, 1fr) .58cm;
    }

    .label-canvas--4x3 .label-meta > div {
        padding: .025cm .025cm;
    }

    .label-canvas--4x3 .label-meta span {
        font-size: .10cm;
        letter-spacing: 0;
    }

    .label-canvas--4x3 .label-meta strong {
        overflow: hidden;
        font-size: .17cm;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .label-canvas--4x3 .qty-block strong {
        font-size: .23cm;
    }

    .label-canvas--4x3 .qr-value {
        flex: 0 0 .28cm;
        overflow: hidden;
        padding: 0 .02cm;
        font-size: .115cm;
        white-space: nowrap;
        text-overflow: ellipsis;
    }

    .label-canvas--4x3 .print-audit {
        min-height: 0;
        grid-template-columns: 1fr;
        grid-template-rows: 1fr 1fr;
    }

    .label-canvas--4x3 .print-audit > div {
        min-height: 0;
        padding: .015cm .025cm;
    }

    .label-canvas--4x3 .print-audit > div + div {
        border-left: 0;
        border-top: .02cm solid #111827;
    }

    .label-canvas--4x3 .print-audit span {
        font-size: .10cm;
        letter-spacing: 0;
    }

    .label-canvas--4x3 .print-audit strong {
        font-size: .12cm;
        line-height: 1.08;
    }

    @media print {
        html,
        body {
            width: 4cm !important;
            height: 3cm !important;
        }

        .print-page {
            width: 4cm !important;
            height: 3cm !important;
            min-height: 3cm !important;
        }

        .label-canvas--4x3 {
            width: 4cm !important;
            height: 3cm !important;
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

    <article class="label-canvas{{ $labelSize === '4x3' ? ' label-canvas--4x3' : '' }}" aria-label="Label QR {{ $item->code }}">
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

    <p class="print-hint screen-only">Ukuran: {{ $labelSize === '4x3' ? '4 × 3 cm' : '10 × 10 cm' }}. Gunakan skala 100%, margin “None/Tidak ada”, dan nonaktifkan header/footer pada dialog print. Pastikan ukuran kertas printer sesuai.</p>
</div>
@endsection
