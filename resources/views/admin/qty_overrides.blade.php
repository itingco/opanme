@extends('layouts.app')

@section('title', 'Admin Qty Override')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">ADMIN</div>
        <h1>Qty Override</h1>
        <p class="muted">
            Pilih draft Invoice atau Good Transfer, lalu buka detail untuk melakukan
            Qty Override menggunakan OTP.
        </p>
    </div>

    <a class="btn btn-secondary" href="{{ route('supervisor.otp') }}">
        Generate / Lihat OTP
    </a>
</div>

<div class="alert alert-warning-soft">
    Qty Override tidak mengubah Qty pada AR_Invoices / IC_Mutations.
    Fitur ini hanya mengubah hasil checking item menjadi <strong>OVERRIDE</strong>
    agar dapat dilanjutkan ke final save oleh Checker.
</div>

<div class="card table-card">
    <div style="padding:16px 18px 0;">
        <div class="eyebrow">INVOICE</div>
        <h2 style="margin-top:4px;">Draft Invoice Check</h2>
    </div>

    <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>Invoice</th>
                <th>Company</th>
                <th>Customer</th>
                <th>Picker</th>
                <th>Checker</th>
                <th>Mulai</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            @forelse($invoiceDrafts as $row)
                <tr>
                    <td><strong>{{ $row->invoice_number }}</strong></td>
                    <td>{{ $row->company }}</td>
                    <td>{{ $row->customer_name ?: '-' }}</td>
                    <td>{{ optional($row->picker)->name ?: '-' }}</td>
                    <td>{{ optional($row->checker)->name ?: '-' }}</td>
                    <td>{{ optional($row->started_at)->format('d-m-Y H:i:s') }}</td>
                    <td>
                        <a class="btn btn-warning btn-sm" href="{{ route('checks.show', $row) }}">
                            Open Qty Override
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty">Tidak ada draft Invoice.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card table-card" style="margin-top:18px;">
    <div style="padding:16px 18px 0;">
        <div class="eyebrow">GOOD TRANSFER</div>
        <h2 style="margin-top:4px;">Draft Good Transfer Check</h2>
    </div>

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
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            @forelse($transferDrafts as $row)
                <tr>
                    <td><strong>{{ $row->mutation_number }}</strong></td>
                    <td>{{ $row->company }}</td>
                    <td>{{ $row->source_warehouse_name ?: '-' }}</td>
                    <td>{{ $row->destination_warehouse_name ?: '-' }}</td>
                    <td>{{ optional($row->picker)->name ?: '-' }}</td>
                    <td>{{ optional($row->checker)->name ?: '-' }}</td>
                    <td>{{ optional($row->started_at)->format('d-m-Y H:i:s') }}</td>
                    <td>
                        <a class="btn btn-warning btn-sm" href="{{ route('good-transfers.show', $row) }}">
                            Open Qty Override
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="empty">Tidak ada draft Good Transfer.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
