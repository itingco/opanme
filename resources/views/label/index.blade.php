@extends('label.layout')

@section('title', 'Cetak Label QR')

@push('head')
<style>
    .label-size-fieldset {
        border: 0;
        padding: 0;
        margin: 0;
        min-width: 0;
    }

    .label-size-fieldset legend {
        padding: 0;
        margin-bottom: 10px;
        font-weight: 750;
    }

    .label-size-options {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .label-size-option {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
        padding: 16px;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        background: #fff;
        cursor: pointer;
    }

    .label-size-option:has(input:checked) {
        border-color: #2457d6;
        background: #f0f5ff;
        box-shadow: 0 0 0 2px rgba(36, 87, 214, .12);
    }

    .label-size-option:focus-within {
        outline: 2px solid #2457d6;
        outline-offset: 2px;
    }

    .label-size-option input {
        flex: 0 0 auto;
        width: 18px;
        height: 18px;
        margin: 0;
        accent-color: #2457d6;
        cursor: pointer;
    }

    .label-size-option__text strong,
    .label-size-option__text small {
        display: block;
    }

    .label-size-option__text strong {
        color: #172033;
    }

    .label-size-option__text small {
        margin-top: 3px;
        color: #667085;
        line-height: 1.4;
    }

    .label-size-fieldset > small {
        display: block;
        margin-top: 9px;
        color: #667085;
    }

    @media (max-width: 480px) {
        .label-size-options {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<main class="page-shell">
    <section class="entry-card" aria-labelledby="page-title">
        <div class="entry-card__header">
            <div>
                <p class="eyebrow">LABEL BARANG</p>
                <h1 id="page-title">Cetak Label QR</h1>
                <p class="subtitle">Pilih kode barang dari database, masukkan qty dan ukuran label, lalu preview sebelum mencetak.</p>
            </div>
            <div class="label-size-chip">2 Ukuran</div>
        </div>

        @if ($errors->any())
            <div class="alert alert--error" role="alert">
                <strong>Label belum dapat dibuat.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('label.preview') }}" class="label-form" id="label-form" novalidate>
            @csrf

            <div class="field field--lookup">
                <label for="item_code">Kode barang</label>
                <div class="lookup-wrap">
                    <input
                        id="item_code"
                        name="item_code"
                        type="text"
                        value="{{ old('item_code') }}"
                        maxlength="100"
                        autocomplete="off"
                        placeholder="Contoh: CDLI2002"
                        aria-describedby="item-code-help"
                        aria-autocomplete="list"
                        aria-controls="item-results"
                        required
                    >
                    <span class="lookup-status" id="lookup-status" aria-live="polite"></span>
                    <div class="lookup-results" id="item-results" role="listbox" hidden></div>
                </div>
                <small id="item-code-help">Ketik minimal satu karakter, kemudian pilih barang yang sesuai.</small>
            </div>

            <div class="field">
                <label for="item_name">Nama barang</label>
                <input
                    id="item_name"
                    type="text"
                    value=""
                    placeholder="Nama barang muncul otomatis"
                    readonly
                    tabindex="-1"
                >
            </div>

            <div class="field">
                <label for="qty">Qty</label>
                <input
                    id="qty"
                    name="qty"
                    type="number"
                    value="{{ old('qty', 1) }}"
                    min="1"
                    max="999999"
                    step="1"
                    inputmode="numeric"
                    required
                >
                <small>Qty akan digabung ke nilai QR. Contoh: <code>CDLI2002-25</code>.</small>
            </div>

            <fieldset class="label-size-fieldset">
                <legend>Ukuran label</legend>
                <div class="label-size-options">
                    <label class="label-size-option">
                        <input type="radio" name="label_size" value="10x10" @checked(old('label_size', '10x10') === '10x10') required>
                        <span class="label-size-option__text">
                            <strong>10 × 10 cm</strong>
                            <small>Ukuran standar</small>
                        </span>
                    </label>
                    <label class="label-size-option">
                        <input type="radio" name="label_size" value="4x3" @checked(old('label_size', '10x10') === '4x3') required>
                        <span class="label-size-option__text">
                            <strong>4 × 3 cm</strong>
                            <small>Label kecil, landscape</small>
                        </span>
                    </label>
                </div>
                <small>Ukuran yang dipilih digunakan pada preview dan halaman cetak.</small>
            </fieldset>

            <div class="form-summary">
                <div>
                    <span>Nilai QR</span>
                    <strong id="qr-preview">-</strong>
                </div>
                <p>Satu kali proses menghasilkan satu label.</p>
            </div>

            <button type="submit" class="button button--primary" id="submit-button">
                Buat &amp; Preview Label
            </button>
            <a href="{{ url('/') }}" class="button button--secondary">Kembali ke Stock Opname</a>
        </form>
    </section>
</main>
@endsection

@push('scripts')
<script>
    window.itemLookupConfig = {
        endpoint: @json(route('label.items.index')),
        previousCode: @json(old('item_code')),
    };
</script>
<script src="{{ asset('label-assets/js/item-lookup.js') }}" defer></script>
@endpush
