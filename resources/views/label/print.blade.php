@extends('label.layout')
@section('title', 'Label '.$item->code)
@push('head')
<style>
@page{size:{{ $labelSize === '4x3' ? '40mm 30mm' : '100mm 100mm' }};margin:0}
.label-canvas--compact{display:grid;overflow:hidden;box-sizing:border-box;border:0;background:#fff;color:#000;font-family:Arial,Helvetica,sans-serif;box-shadow:0 15px 35px rgba(22,31,50,.15)}
.label-canvas--4x3{width:40mm;height:30mm;min-width:40mm;min-height:30mm;grid-template-columns:18.7mm minmax(0,1fr);grid-template-rows:minmax(0,1fr) 3.6mm}
.label-canvas--10x10{width:100mm;height:100mm;min-width:100mm;min-height:100mm;grid-template-columns:47mm minmax(0,1fr);grid-template-rows:minmax(0,1fr) 11mm}
.label-canvas--compact .compact-qr{grid-column:1;grid-row:1;min-width:0;min-height:0;display:grid;place-items:center;padding:0}
.label-canvas--compact .compact-qr svg{display:block;max-width:100%;max-height:100%;shape-rendering:crispEdges}
.label-canvas--4x3 .compact-qr svg{width:18mm;height:18mm}.label-canvas--10x10 .compact-qr svg{width:44mm;height:44mm}
.label-canvas--compact .compact-details{grid-column:2;grid-row:1;min-width:0;min-height:0;display:flex;flex-direction:column;align-items:stretch;overflow:hidden}
.label-canvas--4x3 .compact-details{padding:.65mm .75mm 0 .2mm}.label-canvas--10x10 .compact-details{padding:6mm 6mm 0 1mm}
.label-canvas--compact .compact-name{min-width:0;min-height:0;flex:0 1 auto;overflow:hidden;overflow-wrap:anywhere;white-space:normal;font-weight:700;line-height:1.08}
.label-canvas--4x3 .compact-name{max-height:13mm;padding:.45mm 0 .35mm;font-size:7.5pt}.label-canvas--10x10 .compact-name{max-height:48mm;padding:2mm 0 1.5mm;font-size:19pt}
.label-canvas--compact .compact-fields{min-width:0;display:grid;grid-template-columns:minmax(0,1.25fr) minmax(0,.9fr);align-items:center}
.label-canvas--4x3 .compact-fields{flex:0 0 8.2mm;margin-top:.8mm;padding:.3mm 0}.label-canvas--10x10 .compact-fields{flex:0 0 25mm;margin-top:3mm;padding:1mm 0}
.label-canvas--compact .compact-fields>div{min-width:0;display:flex;flex-direction:column;justify-content:center}.label-canvas--compact .compact-fields>div+div{border-left:1px solid #777;padding-left:3px}
.compact-field-caption,.compact-field-value{font-weight:700;white-space:nowrap}.compact-field-value{min-width:0;overflow:hidden;line-height:1.1}
.label-canvas--4x3 .compact-field-caption{font-size:4.3pt}.label-canvas--10x10 .compact-field-caption{font-size:10pt}
.label-canvas--4x3 .compact-field-value{font-size:9pt;margin-top:.4mm}.label-canvas--10x10 .compact-field-value{font-size:22pt;margin-top:1.2mm}
.compact-unit{display:flex;gap:4px;align-items:center;min-width:0;white-space:nowrap;font-weight:700}.label-canvas--4x3 .compact-unit{font-size:5pt;margin-top:.3mm}.label-canvas--10x10 .compact-unit{font-size:12pt;margin-top:2mm}
.compact-audit{grid-column:1/-1;grid-row:2;display:flex;justify-content:flex-end;align-items:center;min-width:0;overflow:hidden;white-space:nowrap;border-top:1px solid #999;font-weight:400}
.label-canvas--4x3 .compact-audit{margin:0 .9mm;font-size:5.5pt}.label-canvas--10x10 .compact-audit{margin:0 5mm;font-size:11pt}
@media print{html,body,.print-page{margin:0!important;padding:0!important;overflow:hidden!important;background:#fff!important}.print-page{display:flex!important;justify-content:center!important;align-items:center!important}.label-canvas--compact{margin:0!important;box-shadow:none!important;break-inside:avoid;page-break-inside:avoid}
@if($labelSize==='4x3')
html,body,.print-page{
    width:40mm!important;
    min-width:40mm!important;
    max-width:40mm!important;
    height:30mm!important;
    min-height:30mm!important;
    max-height:30mm!important;
}
.print-page{
    position:relative!important;
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
}
.label-canvas--4x3{
    width:40mm!important;
    min-width:40mm!important;
    max-width:40mm!important;
    height:30mm!important;
    min-height:30mm!important;
    max-height:30mm!important;
}
@else html,body,.print-page,.label-canvas--10x10{width:100mm!important;min-width:100mm!important;max-width:100mm!important;height:100mm!important;min-height:100mm!important;max-height:100mm!important}
@endif }
</style>
@endpush
@section('content')
<div class="print-page"><div class="screen-toolbar screen-only"><a class="button button--secondary" href="{{ route('label.index') }}">← Kembali</a><button type="button" class="button button--primary" onclick="window.print()">Cetak Label</button></div>
<article class="label-canvas label-canvas--compact label-canvas--{{ $labelSize }}" aria-label="Label QR {{ $item->code }}">
<div class="compact-qr" aria-label="QR berisi {{ $qrValue }}">{!! SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(520)->margin(1)->errorCorrection('H')->generate($qrValue) !!}</div>
<div class="compact-details"><div class="compact-name" id="compact-item-name">{{ $item->name }}</div><div class="compact-fields"><div><span class="compact-field-caption">KODE BARANG</span><strong class="compact-field-value">{{ $item->code }}</strong></div><div><span class="compact-field-caption">QTY</span><strong class="compact-field-value">{{ $qty }}</strong></div></div><div class="compact-unit">SATUAN: {{ $uom }} @if($barcode) · BARCODE @else · QR KODE-QTY @endif</div></div>
<div class="compact-audit">{{ $printedBy }}, {{ $printedAt->format('d-m-Y H:i:s') }}</div></article>
@if($barcode)<p class="print-hint screen-only">QR {{ $qrValue }} berisi barcode master satuan {{ $uom }} dengan jumlah {{ $qty }} satuan. WarehouseCheck mengalikan jumlah dengan ratio UOM.</p>@else<p class="print-hint screen-only">Tidak ada master barcode. QR memakai kode barang-qty: {{ $qrValue }}.</p>@endif
</div>
@endsection
@push('scripts')
<script>
(function(){function fit(){const canvas=document.querySelector('.label-canvas--compact');if(!canvas)return;const large=canvas.classList.contains('label-canvas--10x10');const name=document.getElementById('compact-item-name');let n=large?19:7.5;name.style.fontSize=n+'pt';while(name.scrollHeight>name.clientHeight+1&&n>(large?10:4.5)){n-=(large?.5:.25);name.style.fontSize=n+'pt'}const fields=[...document.querySelectorAll('.compact-field-value')];let f=large?22:9;fields.forEach(e=>e.style.fontSize=f+'pt');while(fields.some(e=>e.scrollWidth>e.clientWidth+1)&&f>(large?12:5)){f-=(large?.5:.25);fields.forEach(e=>e.style.fontSize=f+'pt')}}if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',fit);else fit();window.addEventListener('beforeprint',fit)})();
</script>
@endpush
