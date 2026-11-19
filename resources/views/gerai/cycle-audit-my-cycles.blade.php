@extends('layouts.app')
@section('title','Batalkan Cycle Saya')
@section('content')
<div class="page-heading"><h1>Cycle Gerai yang Saya Buat</h1><p>Pembatalan tidak menghapus riwayat scan, hanya mengubah status ke BATAL.</p></div>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Cycle</th><th>Gudang</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr></thead><tbody>
@forelse($cycles as $cycle)
<tr><td>{{ $cycle->cycle_no }}</td><td>{{ $cycle->source_database }} / {{ $cycle->warehouse_code }}</td><td>{{ $cycle->status }}</td><td>{{ $cycle->started_at?->format('d/m/Y H:i') }}</td><td>
<form action="{{ route('gerai.admin.cancel',$cycle) }}" method="POST" onsubmit="return confirm('Batalkan cycle ini? Riwayat scan tetap tersimpan.')">@csrf @method('PATCH')<button type="submit" class="btn small">Batalkan</button></form></td></tr>
@empty <tr><td colspan="5">Tidak ada cycle OPEN/CLOSED yang Anda buat.</td></tr> @endforelse
</tbody></table></div>{{ $cycles->links() }}</section>
@endsection
