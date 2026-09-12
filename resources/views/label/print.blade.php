@extends('label.layout')

@section('title', 'Label '.$item->code)

@push('head')
<style>
    @page {
        size: 10cm 10cm;
        margin: 0;
    }
</style>
@endpush

@section('content')
<div class="print-page">
    <div class="screen-toolbar screen-only">
        <a href="{{ route('label.index') }}" class="button button--secondary">← Kembali</a>
        <button type="button" class="button button--primary" onclick="window.print()">Cetak Label</button>
    </div>

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

    <p class="print-hint screen-only">Gunakan skala 100%, margin “None/Tidak ada”, dan nonaktifkan header/footer pada dialog print.</p>
</div>
@endsection
