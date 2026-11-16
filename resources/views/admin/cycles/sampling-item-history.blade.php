@extends('layouts.app')
@section('title', 'Riwayat Sampling Item')
@section('content')
<div class="page-heading">
  <div>
    <h1>Riwayat Sampling — {{ $item->item_code }}</h1>
    <p>{{ $item->item_name }} · {{ $warehouse->warehouse_code }} · {{ $cycle->source_database }}</p>
  </div>
  <a class="btn" href="{{ route('admin.cycles.summary', $cycle) }}">Kembali ke Summary</a>
</div>
<section class="panel">
  <p>Menampilkan semua riwayat Sampling Gudang yang sudah <strong>CLOSED</strong> untuk <strong>item dan warehouse ini</strong>. Secara otomatis ditampilkan 3 bulan terakhir; tanggal dapat diubah bebas untuk melihat periode lainnya.</p>
  <form method="GET" class="filter-row" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-bottom:16px">
    <label>Tanggal dari <input type="date" name="from" value="{{ $dateFrom['from'] ?? '' }}"></label>
    <label>Tanggal sampai <input type="date" name="to" value="{{ $dateFrom['to'] ?? '' }}"></label>
    <button class="btn primary" type="submit">Terapkan</button>
    <a class="btn" href="{{ url()->current() }}">3 Bulan Terakhir</a>
  </form>
  <p style="margin:0 0 12px;color:#64748b;font-size:13px">Periode ditampilkan: <strong>{{ $dateFrom['from'] ?: 'Semua tanggal' }}</strong> s.d. <strong>{{ $dateFrom['to'] ?: 'Semua tanggal' }}</strong>. Kosongkan salah satu tanggal untuk filter tanpa batas di sisi tersebut.</p>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Tanggal Tutup</th><th>Periode / Lokasi</th><th>Checker / Waktu</th><th>Validator / Waktu</th><th class="num">Stok Sistem</th><th class="num">Stok Validasi</th><th class="num">Qty Fisik</th><th>Hasil / Kondisi Stok</th><th>Catatan</th></tr></thead>
      <tbody>
      @forelse($history as $row)
        <tr>
          <td>{{ $row->closed_at ? \Carbon\Carbon::parse($row->closed_at)->format('d/m/Y H:i') : '-' }}</td>
          <td><strong>{{ $row->cycle_no }}</strong><small>{{ $row->location ?: '-' }}</small></td>
          <td>{{ $row->checker_name ?: '-' }}<small>{{ $row->checked_at ? \Carbon\Carbon::parse($row->checked_at)->format('d/m/Y H:i') : '-' }}</small></td>
          <td>{{ $row->validator_name ?: '-' }}<small>{{ $row->validated_at ? \Carbon\Carbon::parse($row->validated_at)->format('d/m/Y H:i') : '-' }}</small></td>
          <td class="num">{{ number_format((float)$row->system_qty,4,'.',',') }}</td>
          <td class="num">{{ number_format((float)($row->validation_system_qty ?? $row->system_qty),4,'.',',') }}</td>
          <td class="num">{{ $row->allocated_physical_qty === null ? '-' : number_format((float)$row->allocated_physical_qty,4,'.',',') }}</td>
          <td><strong>{{ $row->result === 'MATCH' ? 'COCOK' : ($row->result === 'MISMATCH' ? 'SELISIH' : ($row->result ?: '-')) }}</strong></td>
          <td>{{ $row->checker_comment ?: '-' }}<small>{{ $row->validation_note ?: '' }}</small></td>
        </tr>
      @empty
        <tr><td colspan="9">Tidak ada riwayat Sampling Gudang CLOSED untuk item, warehouse, dan rentang tanggal tersebut.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  {{ $history->links() }}
</section>
@endsection
