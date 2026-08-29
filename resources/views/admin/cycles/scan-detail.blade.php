@extends('layouts.app')
@section('title','Detail Scan')
@section('content')
@php($first=$scans->first())
<div class="page-heading"><div><h1>{{ $first->item_code }}</h1><p>{{ $first->item_name }} · {{ $warehouse->warehouse_code }}</p></div><a class="btn" href="{{ route('admin.cycles.summary',$cycle) }}">← Summary</a></div>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Checker</th><th>Lokasi/Rak</th><th>Barcode</th><th>UOM</th><th class="num">Ratio Used</th><th class="num">Physical Qty</th></tr></thead><tbody>@foreach($scans as $scan)<tr><td>{{ $scan->scanned_at->format('d/m H:i:s') }}</td><td>{{ $scan->checker?->name ?? $scan->checker_id }}</td><td>{{ $scan->location }}</td><td>{{ $scan->alias_code }}</td><td>{{ $scan->uom_code }}</td><td class="num">{{ number_format((float)$scan->ratio_used,4,'.',',') }}</td><td class="num">{{ number_format((float)$scan->physical_qty,4,'.',',') }}</td></tr>@endforeach</tbody></table></div></section>
@endsection
