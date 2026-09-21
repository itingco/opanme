@extends('layouts.app')

@section('title', 'Good Transfer Checking')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">GOOD TRANSFER</div>
        <h1>Good Transfer Checking</h1>
        <p class="muted">
            Riwayat checking IC_Mutations dari gudang asal ke gudang tujuan.
        </p>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn btn-secondary" href="{{ route('dashboard') }}">
            Invoice
        </a>

        @if(auth()->user()->role === 'checker')
            <a class="btn btn-primary" href="{{ route('checks.create', ['document_type' => 'good_transfer']) }}">
                + New Check
            </a>
        @endif
    </div>
</div>

<div class="card">
    <form method="GET" class="filter-grid">
        <label>
            Mutation Number
            <input
                name="mutation"
                value="{{ request('mutation') }}"
                placeholder="Nomor Good Transfer"
            >
        </label>

        <label>
            Company
            <select name="company">
                <option value="">Semua</option>
                <option value="INGCO" @selected(request('company') === 'INGCO')>INGCO</option>
                <option value="SMI" @selected(request('company') === 'SMI')>SMI</option>
            </select>
        </label>

        <label>
            Status
            <select name="status">
                <option value="">Semua</option>
                @foreach(['DRAFT','FINALIZING','COMPLETED'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>
                        {{ $status }}
                    </option>
                @endforeach
            </select>
        </label>

        <button class="btn btn-secondary align-end">Filter</button>
    </form>
</div>

<div class="card table-card">
    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>Mutation</th>
                <th>Company</th>
                <th>Asal</th>
                <th>Tujuan</th>
                <th>Picker</th>
                <th>Checker</th>
                <th>Mulai</th>
                <th>Selesai</th>
                <th>Salah Scan</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>
                        <a href="{{ route('good-transfers.show', $row) }}">
                            <strong>{{ $row->mutation_number }}</strong>
                        </a>
                    </td>
                    <td>{{ $row->company }}</td>
                    <td>{{ $row->source_warehouse_name ?: '-' }}</td>
                    <td>{{ $row->destination_warehouse_name ?: '-' }}</td>
                    <td>{{ optional($row->picker)->name }}</td>
                    <td>{{ optional($row->checker)->name }}</td>
                    <td>{{ optional($row->started_at)->format('d-m-Y H:i:s') }}</td>
                    <td>{{ optional($row->completed_at)->format('d-m-Y H:i:s') ?? '-' }}</td>
                    <td><span class="badge badge-danger">{{ $row->scan_errors_count }}</span></td>
                    <td>
                        <span class="badge badge-{{ strtolower($row->status) }}">
                            {{ $row->status }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="empty">Belum ada data Good Transfer.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($rows->hasPages())
        <div class="pagination-simple">
            @if($rows->onFirstPage())
                <span class="btn btn-light btn-sm disabled">← Previous</span>
            @else
                <a class="btn btn-light btn-sm" href="{{ $rows->previousPageUrl() }}">← Previous</a>
            @endif

            <span class="page-info">
                Page {{ $rows->currentPage() }} / {{ $rows->lastPage() }}
            </span>

            @if($rows->hasMorePages())
                <a class="btn btn-light btn-sm" href="{{ $rows->nextPageUrl() }}">Next →</a>
            @else
                <span class="btn btn-light btn-sm disabled">Next →</span>
            @endif
        </div>
    @endif
</div>
@endsection
