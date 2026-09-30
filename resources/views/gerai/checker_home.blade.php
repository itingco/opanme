@extends('layouts.app')
@section('title','Sampling Opname Harian')
@section('page-class','sampling-gerai-home sampling-mobile-page')
@section('content')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/sampling.css') }}?v=20260930mobile">
@endpush
<div class="page-heading">
    <div>
        <h1>Sampling Opname Harian</h1>
        <p>Pilih gudang yang menjadi akses Anda, lalu mulai scan barcode barang untuk mencocokkan stok sistem dengan kondisi fisik.</p>
    </div>
</div>

<div class="split-grid">
    <section class="panel">
        <div class="panel-head"><h2>Mulai Sampling Baru</h2></div>

        @if(empty($assignments))
            <div class="alert danger">
                Akun ini belum memiliki assignment gudang. Hubungi Admin Gerai untuk memberikan akses gudang terlebih dahulu.
            </div>
        @else
            <form method="POST" action="{{ route('gerai.checker.daily.start') }}" class="stack-form">
                @csrf
                <label>
                    Database & Gudang
                    <select name="warehouse_key" required>
                        <option value="">Pilih gudang...</option>
                        @foreach($assignments as $assignment)
                            <option value="{{ $assignment['source_database'] }}|{{ $assignment['warehouse_id'] }}" @selected(old('warehouse_key') === ($assignment['source_database'].'|'.$assignment['warehouse_id']))>
                                {{ $assignment['source_database'] }} · {{ $assignment['warehouse_code'] }} · {{ $assignment['warehouse_name'] }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Lokasi / Rak
                    <input type="text" name="location" value="{{ old('location') }}" maxlength="255" placeholder="Contoh: Rak A, Display Depan, Gudang Belakang" required>
                </label>

                <div class="info-lite">
                    Setelah cycle dibuat, scan barcode barang. Sistem akan menampilkan Qty stok ERP saat ini. Pilih <strong>Stok Cocok</strong> bila fisik sesuai, atau <strong>Ada Selisih</strong> lalu masukkan Qty fisik sebenarnya.
                </div>

                <button class="btn primary big full" type="submit">Mulai Scan Barcode</button>
            </form>
        @endif
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Cycle Saya</h2></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Cycle</th>
                        <th>Database / Gudang</th>
                        <th>Lokasi</th>
                        <th>Mulai</th>
                        <th>Status</th>
                        <th class="num">Item</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($cycles as $cycle)
                    <tr>
                        <td data-label="Cycle">
                            <strong>{{ $cycle->cycle_no }}</strong>
                            <small>{{ (int)$cycle->created_by === (int)$user->id ? 'Dibuat sendiri' : 'Ditugaskan Admin' }}</small>
                        </td>
                        <td data-label="Database / Gudang">
                            <strong>{{ $cycle->source_database }} · {{ $cycle->warehouse_code }}</strong>
                            <small>{{ $cycle->warehouse_name }}</small>
                        </td>
                        <td data-label="Lokasi">{{ $cycle->location }}</td>
                        <td data-label="Mulai">{{ $cycle->started_at?->format('d/m/Y H:i') }}</td>
                        <td data-label="Status"><span class="status {{ strtolower($cycle->status) }}">{{ $cycle->status }}</span></td>
                        <td data-label="Item" class="num">{{ number_format($cycle->checks_count) }}</td>
                        <td data-label="Aksi">
                            @if($cycle->isOpen())
                                <a class="btn small primary" href="{{ route('gerai.checker.scan',$cycle) }}">Scan / Lanjut</a>
                            @else
                                <a class="btn small" href="{{ route('gerai.checker.scan',$cycle) }}">Lihat Hasil</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">Belum ada sampling harian untuk akun ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $cycles->links() }}
    </section>
</div>
@endsection
