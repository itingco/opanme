@extends('layouts.app')

@section('title', 'Dashboard Checking')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">WAREHOUSE CHECKING</div>
        <h1>Dashboard Checking</h1>
        <p class="muted">
            Invoice dan Good Transfer ditampilkan dalam satu halaman.
        </p>
    </div>

    @if(auth()->user()->role === 'checker')
        <a class="btn btn-primary" href="{{ route('checks.create') }}">
            + New Check
        </a>
    @endif
</div>

<div class="card">
    <form method="GET" class="filter-grid">
        <label>
            Nomor Dokumen
            <input
                name="document"
                value="{{ request('document') }}"
                placeholder="Invoice / Mutation Number"
            >
        </label>

        <label>
            Jenis Dokumen
            <select name="document_type">
                <option value="">Semua</option>
                <option value="invoice" @selected(request('document_type') === 'invoice')>
                    Invoice
                </option>
                <option value="good_transfer" @selected(request('document_type') === 'good_transfer')>
                    Good Transfer
                </option>
            </select>
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
                <th>Jenis Dokumen</th>
                <th>Nomor Dokumen</th>
                <th>Company</th>
                <th>Customer / Perpindahan Gudang</th>
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
                        @if($row->document_type === 'invoice')
                            <span class="badge badge-ok">Invoice</span>
                        @else
                            <span class="badge badge-warning">Good Transfer</span>
                        @endif
                    </td>
                    <td>
                        @if($row->document_type === 'invoice')
                            <a href="{{ route('checks.show', $row->id) }}">
                                <strong>{{ $row->document_number }}</strong>
                            </a>
                        @else
                            <a href="{{ route('good-transfers.show', $row->id) }}">
                                <strong>{{ $row->document_number }}</strong>
                            </a>
                        @endif
                    </td>
                    <td>{{ $row->company }}</td>
                    <td>{{ $row->context }}</td>
                    <td>{{ $row->picker_name }}</td>
                    <td>{{ $row->checker_name }}</td>
                    <td>{{ optional($row->started_at)->format('d-m-Y H:i:s') ?: '-' }}</td>
                    <td>{{ optional($row->completed_at)->format('d-m-Y H:i:s') ?: '-' }}</td>
                    <td>
                        <span class="badge badge-danger">
                            {{ $row->scan_errors_count }}
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-{{ strtolower($row->status) }}">
                            {{ $row->status }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="empty">
                        Belum ada data checking.
                    </td>
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
