@extends('layouts.app')

@section('title', 'New Check')

@section('content')
@php
    $selectedType = old('document_type', request('document_type', 'invoice'));
@endphp

<div class="page-head">
    <div>
        <div class="eyebrow">WAREHOUSE CHECKING</div>
        <h1>New Check</h1>
        <p class="muted">
            Satu halaman untuk memulai checking Invoice atau Good Transfer.
        </p>
    </div>
</div>

<div class="card narrow">
    <form method="POST" action="{{ route('checks.load') }}" class="stack" id="newCheckForm">
        @csrf

        <label>
            Jenis Dokumen
            <select name="document_type" id="documentType" required autofocus>
                <option value="invoice" @selected($selectedType === 'invoice')>
                    Invoice
                </option>
                <option value="good_transfer" @selected($selectedType === 'good_transfer')>
                    Good Transfer
                </option>
            </select>
        </label>

        <label>
            Company / Database
            <select name="company" required>
                <option value="">-- Pilih --</option>
                <option value="INGCO" @selected(old('company') === 'INGCO')>
                    INGCO · AS_INGCO
                </option>
                <option value="SMI" @selected(old('company') === 'SMI')>
                    SMI · AS_SMI
                </option>
            </select>
        </label>

        <label>
            Nama Picker
            <select name="picker_user_id" required>
                <option value="">-- Pilih Picker --</option>
                @foreach($pickers as $picker)
                    <option
                        value="{{ $picker->id }}"
                        @selected((string) old('picker_user_id') === (string) $picker->id)
                    >
                        {{ $picker->name }}
                    </option>
                @endforeach
            </select>
        </label>

        <label>
            <span id="documentNumberLabel">Nomor Invoice</span>
            <input
                name="document_number"
                id="documentNumber"
                value="{{ old('document_number') }}"
                placeholder="Masukkan nomor invoice"
                required
            >
        </label>

        <div
            id="documentHint"
            class="muted small"
            style="margin-top:-4px;"
        >
            Data akan diambil dari AR_Invoices.
        </div>

        <button class="btn btn-primary btn-lg" id="loadDocumentBtn">
            Load Invoice
        </button>
    </form>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const type = document.getElementById('documentType');
    const label = document.getElementById('documentNumberLabel');
    const input = document.getElementById('documentNumber');
    const hint = document.getElementById('documentHint');
    const button = document.getElementById('loadDocumentBtn');

    function refreshDocumentFields() {
        const isTransfer = type.value === 'good_transfer';

        label.textContent = isTransfer
            ? 'Mutation Number / Nomor Good Transfer'
            : 'Nomor Invoice';

        input.placeholder = isTransfer
            ? 'Masukkan Mutation Number'
            : 'Masukkan nomor invoice';

        hint.textContent = isTransfer
            ? 'Data akan diambil dari IC_Mutations.'
            : 'Data akan diambil dari AR_Invoices.';

        button.textContent = isTransfer
            ? 'Load Good Transfer'
            : 'Load Invoice';
    }

    type.addEventListener('change', refreshDocumentFields);
    refreshDocumentFields();
})();
</script>
@endpush
