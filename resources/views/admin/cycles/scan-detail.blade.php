@extends('layouts.app')
@section('title','Detail Scan')
@section('content')
@php($isNonSystem = isset($nonSystemItem) && $nonSystemItem)
@php($first = $scans->first())
<div class="page-heading">
    <div>
        <h1>{{ $isNonSystem ? $nonSystemItem->alias_code : ($first?->item_code ?? 'Detail Scan') }}</h1>
        <p>
            {{ $isNonSystem ? $nonSystemItem->item_name : ($first?->item_name ?? '-') }} · {{ $warehouse->warehouse_code }}
            @if($isNonSystem)<span class="non-system-row-badge">NON-SYSTEM</span>@endif
        </p>
    </div>
    <a class="btn" href="{{ route('admin.cycles.summary',$cycle) }}">← Summary</a>
</div>
<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Waktu</th><th>Checker</th><th>Lokasi/Rak</th><th>Barcode</th><th>Qty Input</th><th>UOM</th><th class="num">Ratio Used</th><th>Smallest UOM</th><th class="num">Physical Qty</th>
                </tr>
            </thead>
            <tbody>
                @forelse($scans as $scan)
                    <tr>
                        <td>{{ $scan->scanned_at?->format('d/m H:i:s') }}</td>
                        <td>{{ $scan->checker?->name ?? $scan->checker_id }}</td>
                        <td>{{ $scan->location }}</td>
                        <td>{{ $isNonSystem ? $nonSystemItem->alias_code : $scan->alias_code }}</td>
                        <td>{{ $isNonSystem ? number_format((float)$scan->input_qty,4,'.',',') : '1.0000' }}</td>
                        <td>{{ $isNonSystem ? $scan->input_uom_code : $scan->uom_code }}</td>
                        <td class="num">{{ number_format((float)$scan->ratio_used,4,'.',',') }}</td>
                        <td>{{ $isNonSystem ? $scan->smallest_uom_code : '-' }}</td>
                        <td class="num">{{ number_format((float)$scan->physical_qty,4,'.',',') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty">Tidak ada scan fisik untuk item ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
