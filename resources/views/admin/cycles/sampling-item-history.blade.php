@extends('layouts.app')
@section('title', 'Riwayat Sampling Item')
@section('content')
<div class="page-heading">
  <div><h1>Riwayat Sampling — {{ $item->item_code }}</h1>
    <p>{{ $item->item_name }} · {{ $warehouse->warehouse_code }} · {{ $cycle->source_database }}</p></div>
  <a class="btn" href="{{ route('admin.cycles.summary', $cycle) }}">Kembali ke Summary</a>
</div>
<section class="panel">
  <p>Menampilkan <strong>semua status</strong> Sampling Gudang dan Sampling Gerai untuk item, database dan gudang ini. Periode awal 3 bulan terakhir; tanggal bebas diubah.</p>
  <form method="GET" class="filter-row" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-bottom:16px">
    <label>Tanggal dari <input type="date" name="from" value="{{ $dateFrom['from'] ?? '' }}"></label>
    <label>Tanggal sampai <input type="date" name="to" value="{{ $dateFrom['to'] ?? '' }}"></label>
    <button class="btn primary" type="submit">Terapkan</button>
    <a class="btn" href="{{ url()->current() }}">3 Bulan Terakhir</a>
    <a class="btn" href="{{ url()->current() }}?from=&amp;to=">Semua Tanggal</a>
  </form>
  <div class="table-wrap"><table><thead><tr><th>Waktu Sampling</th><th>Jenis / Status</th><th>Periode / Lokasi</th><th>Checker / Waktu</th><th>Validator / Waktu</th><th class="num">Stok Sistem</th><th class="num">Stok Validasi</th><th class="num">Qty Fisik</th><th>Hasil / Kondisi</th><th>Catatan</th></tr></thead><tbody>
  @forelse($history as $row)
    <tr>
      <td>{{ $row->event_at ? \Carbon\Carbon::parse($row->event_at)->format('d/m/Y H:i') : '-' }}</td>
      <td><strong>{{ $row->sampling_type }}</strong><small>{{ $row->cycle_status ?: '-' }}</small></td>
      <td><strong>{{ $row->cycle_no ?: '-' }}</strong><small>{{ $row->location ?: '-' }}</small></td>
      <td>{{ $row->checker_name ?: '-' }}<small>{{ $row->checked_at ? \Carbon\Carbon::parse($row->checked_at)->format('d/m/Y H:i') : '-' }}</small></td>
      <td>{{ $row->validator_name ?: '-' }}<small>{{ $row->validated_at ? \Carbon\Carbon::parse($row->validated_at)->format('d/m/Y H:i') : '-' }}</small></td>
      <td class="num">{{ number_format((float)$row->system_qty,4,'.',',') }}</td>
      <td class="num">{{ $row->validation_system_qty === null ? '-' : number_format((float)$row->validation_system_qty,4,'.',',') }}</td>
      <td class="num">{{ $row->physical_qty === null ? '-' : number_format((float)$row->physical_qty,4,'.',',') }}</td>
      <td><strong>{{ $row->result === 'MATCH' ? 'COCOK' : ($row->result === 'MISMATCH' ? 'SELISIH' : ($row->result ?: 'BELUM DIPERIKSA')) }}</strong></td>
      <td>{{ $row->checker_comment ?: '-' }}<small>{{ $row->validation_note ?: '' }}</small></td>
    </tr>
  @empty
    <tr><td colspan="10">Belum ada sampling yang cocok dengan item, database, gudang, dan tanggal tersebut.</td></tr>
  @endforelse
  </tbody></table></div>
  {{ $history->links() }}
</section>
@endsection
