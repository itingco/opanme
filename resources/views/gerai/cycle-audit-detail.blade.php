@extends('layouts.app')
@section('title','Detail Audit Selisih')
@section('content')
<div class="page-heading"><div><h1>Selisih {{ $number }}</h1><p>Read only. Tidak tersedia override atau perubahan data.</p></div><a class="btn" href="{{ route('cycle-audit.index') }}">Kembali</a></div>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Waktu / Gudang</th><th>Kode</th><th>Nama Barang</th><th>Stok Sistem</th><th>Fisik Final</th><th>Selisih</th></tr></thead><tbody>
@forelse($rows as $r)
@if($kind === 'gerai')
<tr><td>{{ $r->scanned_at?->format('d/m/Y H:i:s') }} · {{ $r->warehouse_code }}</td><td>{{ $r->item_code }}</td><td>{{ $r->item_name }}</td><td>{{ number_format((float)$r->system_qty,4) }}</td><td>{{ number_format((float)$r->physical_qty,4) }}</td><td>{{ number_format((float)$r->physical_qty-(float)$r->system_qty,4) }}</td></tr>
@else
<tr><td>{{ $r->warehouse_code }}</td><td>{{ $r->item_code }}</td><td>{{ $r->item_name }}</td><td>{{ number_format((float)$r->opening_system_qty,4) }}</td><td>{{ number_format((float)$r->final_physical_qty,4) }}</td><td>{{ number_format((float)$r->variance,4) }}</td></tr>
@endif
@empty <tr><td colspan="6">Tidak ada item selisih.</td></tr> @endforelse
</tbody></table></div>{{ $rows->links() }}</section>
@endsection
