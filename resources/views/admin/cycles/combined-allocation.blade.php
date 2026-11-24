@extends('layouts.app')
@section('title','Alokasi Gudang '.$cycle->cycle_no)
@section('content')
@php
    $canEdit = $cycle->status === 'CLOSED' && in_array(auth()->user()->role,['ADMIN','ADMIN_GUDANG'],true);
    $fmt = fn($qty) => rtrim(rtrim(number_format((float)$qty,4,'.',''),'0'),'.');
@endphp
<div class="page-heading"><div><h1>Alokasi Qty per Gudang</h1><p>{{ $cycle->cycle_no }} · {{ $cycle->status }} · Cutoff {{ $cycle->cutoff_date->format('d/m/Y') }}</p></div></div>
@include('admin.cycles.combined-sources')
<style>
    .allocation-editor {padding:12px;background:#f8fafc;border:1px solid #dbe3ee;border-radius:8px}
    .allocation-editor summary {cursor:pointer;font-weight:650;color:#1855b6}
    .allocation-editor-grid {display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin:12px 0}
    .allocation-editor-grid label {display:flex;flex-direction:column;gap:4px;padding:10px;border:1px solid #dbe3ee;border-radius:8px;background:white}
    .allocation-editor-grid input {width:100%;box-sizing:border-box}
    .allocation-editor .allocation-total {font-weight:650;margin:10px 0}
    .allocation-editor .allocation-total.invalid {color:#b91c1c}
    .allocation-editor .allocation-total.valid {color:#166534}
    .allocation-editor-row td {padding:8px 10px 16px}
</style>
<section class="panel" style="padding:16px;margin-bottom:16px">
    <p><strong>Dasar pembagian:</strong> stok opening positif per gudang. Bobot pada alokasi manual menunjukkan persentase qty yang dialokasikan terhadap Final Fisik. Gudang dengan opening, closing dan alokasi sama-sama nol disembunyikan dari tabel dan Excel. Stok negatif tetap ditampilkan. Non-System tetap UNALLOCATED.</p>
    @if($cycle->status === 'OPEN')<p><strong>Alokasi sementara:</strong> hasil mengikuti scan dan override. Tutup cycle untuk mengedit alokasi manual.</p>@endif
    @if($canEdit)<p><strong>Alokasi manual:</strong> buka Edit Alokasi pada barang yang ingin dikoreksi. Jumlah seluruh gudang wajib sama dengan Final Fisik. Gudang ERP yang valid dengan stok nol juga tersedia dalam editor.</p>@endif
    @if($cycle->status === 'FINALIZED')<p><strong>FINALIZED:</strong> seluruh alokasi terkunci.</p>@endif
    @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <form method="GET" action="{{ route('admin.cycles.combined-allocation',$cycle) }}" style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0">
        <input name="search" value="{{ request('search') }}" placeholder="Cari kode/nama barang" type="search">
        <button class="btn primary" type="submit">Cari</button>
        <a class="btn" href="{{ route('admin.cycles.combined-allocation',$cycle) }}">Reset</a>
        <a class="btn" href="{{ route('admin.cycles.summary',$cycle) }}">Kembali Summary</a>
        <a class="btn" href="{{ route('admin.cycles.combined-allocation.export',['cycle'=>$cycle,'search'=>request('search')]) }}">Export Excel (hasil filter)</a>
    </form>
    <div style="overflow:auto">
        <table class="table" style="width:100%;border-collapse:collapse;min-width:950px">
            <thead><tr><th>Item Code / Nama</th><th>Database</th><th>Warehouse</th><th>Opening ERP</th><th>Closing ERP</th><th>Bobot (%)</th><th>Alokasi Fisik</th><th>Selisih</th><th>Catatan</th></tr></thead>
            <tbody>
            @forelse($groups as $group)
                @php($r=$group['row'])
                @foreach($group['details'] as $d)
                <tr style="border-bottom:1px solid #e2e8f0">
                    @if($loop->first)
                    <td rowspan="{{ count($group['details']) }}" style="vertical-align:top;padding:10px"><strong>{{ $r->item_code }}</strong><br>{{ $r->item_name }}<br><small>Total fisik: {{ $fmt($r->final_physical_qty) }} {{ $group['uom'] }}</small><br>
                    @if($group['active'])<small style="color:#166534;font-weight:bold">MANUAL</small>@elseif($group['stale'])<small style="color:#b91c1c;font-weight:bold">PERLU EDIT / RESET</small>@endif</td>
                    @endif
                    <td>{{ $d['database'] }}</td><td>{{ $d['warehouse_code'] }}<br><small>{{ $d['warehouse_name'] }}</small></td>
                    <td>{{ $fmt($d['opening']) }}</td><td>{{ $d['closing']===null?'-':$fmt($d['closing']) }}</td>
                    <td>{{ $d['weight']===null?'-':number_format($d['weight']*100,2).'%' }}</td>
                    <td><strong>{{ $fmt($d['allocated']) }}</strong></td>
                    <td style="color:{{ abs($d['variance'])>0.00005?'#b91c1c':'inherit' }}">{{ $fmt($d['variance']) }}</td><td><small>{{ $d['note'] }}</small></td>
                </tr>
                @endforeach
                @if($canEdit && count($group['editable']))
                <tr class="allocation-editor-row" style="border-bottom:2px solid #cbd5e1"><td colspan="9">
                    <details class="allocation-editor" @if($group['stale']) open @endif>
                        <summary>Edit Alokasi · {{ $r->item_code }} @if($group['active']) (Manual) @endif @if($group['stale']) — Qty final berubah @endif</summary>
                        @if($group['manual'])<p><small>Alokasi terakhir oleh {{ $group['manual']->updated_by_name ?: 'Admin' }} · {{ $group['manual']->updated_at }} · {{ $group['manual']->note }}</small></p>@endif
                        @if($group['stale'])<p style="color:#b91c1c">Final Fisik telah berubah. Angka di bawah adalah saran otomatis terbaru; simpan ulang atau reset sebelum finalisasi.</p>@endif
                        <form method="POST" action="{{ route('admin.cycles.combined-allocation.save',[$cycle,$r->item_id]) }}" class="allocation-form" data-expected="{{ $fmt($r->final_physical_qty) }}">
                            @csrf
                            <input type="hidden" name="expected_qty" value="{{ $fmt($r->final_physical_qty) }}">
                            <div class="allocation-editor-grid">
                                @foreach($group['editable'] as $d)
                                <label><strong>{{ $d['database'] }} · {{ $d['warehouse_code'] }}</strong><small>{{ $d['warehouse_name'] }} · Opening {{ $fmt($d['opening']) }}</small>
                                    <input type="number" inputmode="decimal" min="0" max="999999999999" step="{{ floor((float)$r->final_physical_qty)==(float)$r->final_physical_qty?'1':'0.0001' }}" name="allocation_qty[{{ $d['source_id'] }}]" value="{{ $fmt($d['allocated']) }}" required>
                                </label>
                                @endforeach
                            </div>
                            <div class="allocation-total" aria-live="polite">Final Fisik: {{ $fmt($r->final_physical_qty) }} {{ $group['uom'] }}</div>
                            <label>Alasan perubahan <input type="text" name="note" required maxlength="1000" value="{{ $group['active']?$group['manual']->note:'' }}" placeholder="Contoh: hasil hitung fisik per gudang"></label>
                            <button class="btn primary" type="submit" style="margin-top:10px">Simpan Alokasi</button>
                        </form>
                        @if($group['manual'])
                        <form method="POST" action="{{ route('admin.cycles.combined-allocation.reset',[$cycle,$r->item_id]) }}" style="margin-top:8px" onsubmit="return confirm('Kembalikan item ini ke pembagian otomatis?')">
                            @csrf @method('DELETE')
                            <button class="btn" type="submit">Reset ke Otomatis</button>
                        </form>
                        @endif
                    </details>
                </td></tr>
                @endif
            @empty
                <tr><td colspan="9">Tidak ada barang untuk ditampilkan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:14px">{{ $rows->links() }}</div>
</section>
@endsection
@push('scripts')
<script>
(() => {
    const units = value => {
        const match = /^(\d{1,12})(?:\.(\d{1,4}))?$/.exec(String(value).trim());
        return match ? BigInt(match[1])*10000n + BigInt((match[2] || '').padEnd(4,'0')) : null;
    };
    const display = n => (n / 10000n).toString() + ((n % 10000n) ? '.' + (n % 10000n).toString().padStart(4,'0').replace(/0+$/,'') : '');
    document.querySelectorAll('.allocation-form').forEach(form => {
        const expected = units(form.dataset.expected);
        const inputs = [...form.querySelectorAll('input[name^="allocation_qty["]')];
        const total = form.querySelector('.allocation-total');
        const submit = form.querySelector('button[type="submit"]');
        function sync() {
            const values = inputs.map(input => units(input.value));
            const valid = expected !== null && values.every(v => v !== null && (expected % 10000n !== 0n || v % 10000n === 0n));
            const sum = valid ? values.reduce((a,b)=>a+b,0n) : 0n;
            const ok = valid && sum === expected;
            total.textContent = valid ? `Total alokasi: ${display(sum)} / ${display(expected)} · ${ok?'Sesuai':sum<expected?'Kurang '+display(expected-sum):'Lebih '+display(sum-expected)}` : 'Isi semua qty dengan angka yang valid.';
            total.classList.toggle('valid',ok);
            total.classList.toggle('invalid',!ok);
            submit.disabled = !ok;
        }
        inputs.forEach(input=>input.addEventListener('input',sync));
        sync();
    });
})();
</script>
@endpush
