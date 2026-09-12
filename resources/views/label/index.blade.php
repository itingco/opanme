@extends('label.layout')

@section('title', 'Cetak Label QR')

@section('content')
<main class="page-shell">
    <section class="entry-card" aria-labelledby="page-title">
        <div class="entry-card__header">
            <div>
                <p class="eyebrow">LABEL BARANG</p>
                <h1 id="page-title">Cetak QR 10 × 10 cm</h1>
                <p class="subtitle">Pilih kode barang dari database, masukkan qty, lalu cetak satu label.</p>
            </div>
            <div class="label-size-chip">10 × 10 cm</div>
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
