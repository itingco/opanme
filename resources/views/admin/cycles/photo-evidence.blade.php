@extends('layouts.app')
@section('title', 'Detail Scan & Foto Bukti')
@section('content')
<div class="page-heading">
  <div>
    <h1>Detail Scan & Foto Bukti</h1>
    <p><strong>{{ $item->item_code }}</strong> — {{ $item->item_name }} · {{ $warehouse->warehouse_code }} / {{ $warehouse->warehouse_name }}</p>
    <small>Cycle: {{ $cycle->cycle_no }} · Foto hanya dapat diakses admin.</small>
  </div>
  <a class="btn" href="{{ route('admin.cycles.summary', $cycle) }}">← Summary</a>
</div>
<section class="panel">
  <h2>Riwayat Scan Item Ini</h2>
  <p>Foto yang diwajibkan ketika checker menginput kode barang dan qty secara manual dapat dibuka melalui thumbnail pada baris terkait.</p>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Tanggal / Jam</th><th>User Checker</th><th>Lokasi / Rak</th><th>Kode Input</th><th class="num">Qty Fisik</th><th>Foto Bukti</th></tr></thead>
      <tbody>
      @forelse($scans as $scan)
        <tr>
          <td>{{ \Carbon\Carbon::parse($scan->scanned_at)->format('d/m/Y H:i:s') }}</td>
          <td>{{ $scan->checker_name ?: ('User #'.$scan->checker_id) }}</td>
          <td>{{ $scan->location }}</td>
          <td>{{ $scan->alias_code }}</td>
          <td class="num">{{ number_format((float) $scan->physical_qty, 4, '.', ',') }}</td>
          <td>
            @if($scan->evidence)
              @php($photoUrl = route('admin.cycles.photo-evidence.photo', [$cycle, $warehouse->id, $item->item_id, $scan->id]))
              <button type="button" class="btn small evidence-preview-btn" data-photo-url="{{ $photoUrl }}" data-photo-title="{{ $item->item_code }} · {{ $scan->checker_name ?: 'Checker' }} · {{ $scan->scanned_at }}" aria-label="Preview foto bukti">
                <img src="{{ $photoUrl }}" alt="Bukti foto item {{ $item->item_code }}" loading="lazy" style="width:96px;height:72px;object-fit:cover;border-radius:6px;vertical-align:middle">
                <span>Perbesar</span>
              </button>
            @else
              <span class="muted">Tidak ada foto (scan barcode)</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="empty">Belum ada transaksi scan untuk item ini.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</section>
<dialog id="opname-evidence-preview" aria-label="Preview foto bukti" style="border:0;border-radius:14px;padding:12px;max-width:min(92vw,900px);max-height:92vh;box-shadow:0 20px 70px #0007">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px">
    <strong id="opname-evidence-caption"></strong>
    <button class="btn" type="button" id="opname-evidence-close">Tutup ×</button>
  </div>
  <img id="opname-evidence-image" alt="Foto bukti opname" style="display:block;width:100%;max-height:75vh;object-fit:contain">
</dialog>
@push('scripts')
<script>
(() => {
  const modal = document.getElementById('opname-evidence-preview');
  const picture = document.getElementById('opname-evidence-image');
  const caption = document.getElementById('opname-evidence-caption');
  document.querySelectorAll('.evidence-preview-btn').forEach(button => button.addEventListener('click', () => {
    picture.src = button.dataset.photoUrl;
    caption.textContent = button.dataset.photoTitle || 'Bukti foto';
    if (typeof modal.showModal === 'function') modal.showModal();
    else modal.setAttribute('open', '');
  }));
  document.getElementById('opname-evidence-close').addEventListener('click', () => modal.close());
  modal.addEventListener('click', event => { if (event.target === modal) modal.close(); });
  modal.addEventListener('close', () => { picture.removeAttribute('src'); });
})();
</script>
@endpush
@endsection
