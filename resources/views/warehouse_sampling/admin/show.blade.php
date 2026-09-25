@extends('layouts.app')
@section('title','Kelola Sampling Gudang')
@section('content')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/warehouse-sampling.css') }}?v=20260923j">
@endpush

@php
    $total = (int) $period->items_count;
    $checked = (int) $period->checked_items_count;
    $validated = (int) $period->validated_items_count;
    $pct = $total > 0 ? min(100, ($checked / $total) * 100) : 0;
    $targetCount = $total > 0 ? (int) ceil($total * ((float)$period->target_percentage / 100)) : 0;
    $reached = $targetCount > 0 && $checked >= $targetCount;
@endphp

<div class="page-heading">
    <div>
        <h1>{{ $period->cycle_no }}</h1>
        <p>{{ $period->warehouses->count() }} gudang digabung · {{ $period->location }} · Checker {{ $period->checker?->name ?? '-' }}</p>
    </div>
    <a class="btn" href="{{ route('warehouse.admin.index') }}">← Daftar Periode</a>
</div>

<div class="ws-metrics">
    <div class="ws-metric"><span>Gudang Digabung</span><strong>{{ $period->warehouses->count() }}</strong></div>
    <div class="ws-metric"><span>Item Sampling</span><strong>{{ number_format($total) }}</strong></div>
    <div class="ws-metric"><span>Checker Selesai</span><strong>{{ number_format($checked) }}</strong></div>
    <div class="ws-metric"><span>Admin Validasi</span><strong>{{ number_format($validated) }}</strong></div>
</div>

<section class="panel">
    <div class="panel-head">
        <div><h2>Database & Gudang Periode</h2><small class="ws-note">Snapshot stok item disimpan terpisah untuk setiap gudang di bawah ini.</small></div>
        <span class="ws-badge {{ strtolower($period->status) }}">{{ $period->status }}</span>
    </div>
    <div class="ws-selected-warehouses">
        @foreach($period->warehouses as $warehouse)
            <div class="ws-selected-warehouse-card">
                <span>{{ $warehouse->source_database }}</span>
                <strong>{{ $warehouse->warehouse_code }}</strong>
                <small>{{ $warehouse->warehouse_name }}</small>
            </div>
        @endforeach
    </div>

    <div class="ws-progress {{ $reached?'target-reached':'' }}"><span style="width:{{ $pct }}%"></span></div>
    <p class="ws-note">Progress checker {{ $checked }} / {{ $total }} item ({{ number_format($pct,1) }}%). Target {{ number_format((float)$period->target_percentage,0) }}% = minimal {{ $targetCount }} item. {{ $reached ? 'Target tercapai.' : '' }}</p>
</section>

<section class="panel">
    <div class="panel-head"><h2>Pengaturan Periode</h2></div>
    <form method="POST" action="{{ route('warehouse.admin.update',$period) }}" class="stack-form">
        @csrf @method('PUT')
        <div class="ws-form-grid">
            <label>Target Checker (%)<input name="target_percentage" type="number" min="1" max="100" step="0.01" value="{{ old('target_percentage',$period->target_percentage) }}" required></label>
            <label>Checker Gudang<select name="assigned_checker_id" required>@foreach($checkers as $checker)<option value="{{ $checker->id }}" @selected((string)$period->assigned_checker_id===(string)$checker->id)>{{ $checker->name }} · {{ $checker->username }}</option>@endforeach</select></label>
        </div>
        <label>Area / Lokasi Fisik Gabungan<input name="location" value="{{ old('location',$period->location) }}" maxlength="255" required></label>
        <label>Catatan<textarea name="notes" rows="2">{{ old('notes',$period->notes) }}</textarea></label>
        @if(!$period->isClosed())<button class="btn" type="submit">Simpan Pengaturan</button>@endif
    </form>
</section>

@if($period->isDraft())
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Pilih Item dari Stok Gabungan</h2>
            <small class="ws-note">Item yang sama dari seluruh database/gudang periode digabung menjadi satu baris. Total stok adalah penjumlahan stok seluruh gudang. Detail gudang tetap disimpan sebagai snapshot untuk proses validasi.</small>
        </div>
    </div>

    <form method="POST" action="{{ route('warehouse.admin.items.store',$period) }}" id="ws-bulk-item-form">
        @csrf
        <div id="ws-selected-inputs"></div>
        <div class="ws-stock-toolbar">
            <label class="ws-stock-search">Cari item<input type="search" id="ws-item-search" placeholder="Kode / nama item" autocomplete="off"></label>
            <label class="ws-stock-page-size">Baris<select id="ws-page-size"><option>25</option><option selected>50</option><option>100</option></select></label>
        </div>
        <div class="ws-stock-selectbar">
            <label class="ws-select-all"><input type="checkbox" id="ws-select-page"> Pilih semua di halaman ini</label>
            <span class="ws-note"><b id="ws-selected-count">0</b> item dipilih</span>
            <button class="btn primary" id="ws-save-items" type="submit" disabled>Simpan Item Terpilih</button>
        </div>
        <div class="ws-stock-table-wrap">
            <table class="ws-stock-table ws-stock-table-combined">
                <thead><tr><th class="ws-check-col"></th><th>Item</th><th>UOM</th><th class="num">Total Stok Gabungan</th><th>Sudah Dipilih</th></tr></thead>
                <tbody id="ws-stock-body"><tr><td colspan="5" class="empty">Memuat stok gabungan seluruh gudang...</td></tr></tbody>
            </table>
        </div>
        <div class="ws-stock-footer">
            <span class="ws-note" id="ws-stock-info"></span>
            <div class="ws-stock-pager"><button class="btn small" type="button" id="ws-prev-page">←</button><span class="ws-note" id="ws-page-label"></span><button class="btn small" type="button" id="ws-next-page">→</button></div>
        </div>
    </form>
</section>
@endif


<section class="panel">
    <div class="panel-head">
        <div>
            <h2>{{ $period->isDraft() ? 'Item yang Sudah Dipilih' : 'Hasil Checker & Validasi Admin Gudang' }}</h2>
            <small class="ws-note">
                @if($period->isDraft())
                    Setiap item yang dipilih mewakili stok gabungan seluruh gudang periode. Snapshot per gudang disimpan di belakang untuk validasi.
                @else
                    Checker hanya mengisi Qty Fisik Total gabungan. Saat validasi, sistem membagi Qty Fisik tersebut secara proporsional ke setiap gudang berdasarkan snapshot stok sistem.
                @endif
            </small>
        </div>
    </div>

    <div class="ws-admin-item-list">
        @forelse($items as $item)
            @php
                $salesTotal = 0.0;
                $adjustedSystemTotal = (float) $item->system_qty;

                if ($item->validated_at) {
                    $salesTotal = (float) $item->stocks->sum(fn($stock) => (float)($stock->sales_invoice_qty ?? 0));
                    $adjustedSystemTotal = (float) $item->stocks->sum(fn($stock) => (float)($stock->adjusted_system_qty ?? $stock->system_qty));
                } elseif ($item->checked_at) {
                    $salesTotal = (float) $item->stocks->sum(fn($stock) => (float)(($salesInvoiceAdjustments[$stock->id]['sales_qty'] ?? 0)));
                    $adjustedSystemTotal = (float) $item->stocks->sum(fn($stock) => (float)(($salesInvoiceAdjustments[$stock->id]['adjusted_system_qty'] ?? $stock->system_qty)));
                }

                $totalVariance = ($item->checked_at === null || $item->physical_qty === null)
                    ? null
                    : (float)$item->physical_qty - $adjustedSystemTotal;
            @endphp
            <article class="ws-admin-item-card {{ $item->validated_at ? 'validated' : ($item->checked_at ? 'waiting-validation' : '') }}">
                <div class="ws-admin-item-head">
                    <div>
                        <span class="ws-line-number">#{{ $item->line_no }}</span>
                        <strong>{{ $item->item_code }}</strong>
                        <small>{{ $item->item_name }} · {{ $item->uom_code }}</small>
                    </div>
                    <div class="ws-admin-item-statuses">
                        @if($item->validated_at)
                            <span class="ws-badge open">VALIDATED</span>
                        @elseif($item->checked_at)
                            <span class="ws-badge draft">MENUNGGU VALIDASI</span>
                        @else
                            <span class="ws-badge">MENUNGGU CHECKER</span>
                        @endif
                    </div>
                </div>

                <div class="ws-total-strip ws-total-strip-sales">
                    <span>Snapshot Sistem<b>{{ number_format((float)$item->system_qty,4,'.',',') }} {{ $item->uom_code }}</b></span>
                    <span>Sales Invoice Hari Cek<b class="{{ $salesTotal > 0 ? 'negative' : '' }}">{{ $item->checked_at ? number_format($salesTotal,4,'.',',').' '.$item->uom_code : '-' }}</b></span>
                    <span>Sistem Setelah Sales<b>{{ $item->checked_at ? number_format($adjustedSystemTotal,4,'.',',').' '.$item->uom_code : '-' }}</b></span>
                    <span>Fisik Total Checker<b>{{ ($item->checked_at === null || $item->physical_qty === null) ? '-' : number_format((float)$item->physical_qty,4,'.',',').' '.$item->uom_code }}</b></span>
                    <span>Selisih Total<b class="{{ $totalVariance !== null && $totalVariance < 0 ? 'negative' : ($totalVariance !== null && $totalVariance > 0 ? 'positive' : '') }}">{{ $totalVariance === null ? '-' : number_format($totalVariance,4,'.',',') }}</b></span>
                </div>

                @if($item->checked_at && filled($item->checker_comment))
                    <div class="ws-checker-comment-view">
                        <span>Komentar Checker</span>
                        <p>{{ $item->checker_comment }}</p>
                    </div>
                @endif

                @if($item->checked_at && !$period->isDraft())
                    @if($item->validated_at)
                        <div class="ws-allocation-horizontal-scroll" aria-label="Hasil alokasi fisik per gudang">
                            <div class="ws-allocation-horizontal-track">
                                @foreach($item->stocks as $stock)
                                    @php
                                        $stockSales = (float)($stock->sales_invoice_qty ?? 0);
                                        $stockAdjusted = (float)($stock->adjusted_system_qty ?? $stock->system_qty);
                                        $variance=(float)$stock->allocated_physical_qty-$stockAdjusted;
                                        $invoiceDetails = $stock->sales_invoice_details ?? [];
                                    @endphp
                                    <article class="ws-allocation-card {{ $variance<0?'is-short':($variance>0?'is-over':'is-match') }}">
                                        <div class="ws-allocation-card-head">
                                            <span>{{ $stock->warehouse?->source_database }}</span>
                                            <span class="ws-badge {{ $stock->result==='MATCH'?'open':'closed' }}">{{ $stock->result==='MATCH'?'COCOK':($variance>0?'LEBIH':'KURANG') }}</span>
                                        </div>
                                        <strong class="ws-allocation-warehouse-code">{{ $stock->warehouse?->warehouse_code }}</strong>
                                        <small class="ws-allocation-warehouse-name">{{ $stock->warehouse?->warehouse_name }}</small>
                                        <div class="ws-allocation-card-metrics">
                                            <span>Snapshot Stok<b>{{ number_format((float)$stock->system_qty,4,'.',',') }}</b></span>
                                            <span>Sales Invoice<b class="{{ $stockSales > 0 ? 'negative' : '' }}">{{ number_format($stockSales,4,'.',',') }}</b></span>
                                            <span>Stok Setelah Sales<b>{{ number_format($stockAdjusted,4,'.',',') }}</b></span>
                                            <span>Fisik Dialokasikan<b>{{ number_format((float)$stock->allocated_physical_qty,4,'.',',') }}</b></span>
                                            <span>Selisih<b class="{{ $variance<0?'negative':($variance>0?'positive':'') }}">{{ number_format($variance,4,'.',',') }}</b></span>
                                        </div>
                                        @if($stockSales > 0)
                                            <div class="ws-sales-invoice-list">
                                                <strong>Sales Invoice {{ $stock->sales_invoice_date?->format('d/m/Y') }}</strong>
                                                @foreach($invoiceDetails as $invoice)
                                                    <span>{{ $invoice['invoice_number'] ?? '-' }} <b>{{ number_format((float)($invoice['qty'] ?? 0),4,'.',',') }}</b></span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        </div>
                        <p class="ws-note">Divalidasi oleh {{ $item->validator?->name ?? '-' }} · {{ $item->validated_at?->format('d/m/Y H:i:s') }} @if($item->validation_note) · {{ $item->validation_note }} @endif</p>
                    @else
                        <form method="POST" action="{{ route('warehouse.admin.items.validate',[$period,$item]) }}" class="ws-validation-form" data-physical-total="{{ (float)$item->physical_qty }}" data-system-total="{{ (float)$item->system_qty }}">
                            @csrf @method('PUT')
                            <div class="ws-validation-help">
                                <strong>Validasi Stok Setelah Sales Invoice</strong>
                                <p>Sistem mengecek Sales Invoice pada tanggal checker untuk item + gudang yang sama. Qty invoice keluar dikurangi dari snapshot stok, lalu Qty fisik checker dibagi proporsional berdasarkan stok setelah pengurangan tersebut.</p>
                            </div>
                            <div class="ws-allocation-horizontal-scroll" aria-label="Distribusi fisik proporsional per gudang">
                                <div class="ws-allocation-horizontal-track">
                                    @foreach($item->stocks as $stock)
                                        @php
                                            $salesInfo = $salesInvoiceAdjustments[$stock->id] ?? ['sales_date' => $item->checked_at?->format('Y-m-d'), 'sales_qty' => 0, 'adjusted_system_qty' => (float)$stock->system_qty, 'invoices' => []];
                                            $stockSales = (float)($salesInfo['sales_qty'] ?? 0);
                                            $stockAdjusted = (float)($salesInfo['adjusted_system_qty'] ?? $stock->system_qty);
                                        @endphp
                                        <article class="ws-allocation-card" data-stock-row data-system="{{ $stockAdjusted }}" data-can-allocate="{{ $stock->item_id !== null && $stockAdjusted > 0 ? '1' : '0' }}">
                                            <div class="ws-allocation-card-head">
                                                <span>{{ $stock->warehouse?->source_database }}</span>
                                                <span class="ws-allocation-card-index">Gudang {{ $loop->iteration }}</span>
                                            </div>
                                            <strong class="ws-allocation-warehouse-code">{{ $stock->warehouse?->warehouse_code }}</strong>
                                            <small class="ws-allocation-warehouse-name">{{ $stock->warehouse?->warehouse_name }}</small>
                                            <div class="ws-allocation-card-metrics">
                                                <span>Snapshot Stok<b>{{ number_format((float)$stock->system_qty,4,'.',',') }}</b></span>
                                                <span>Sales Invoice Hari Cek<b class="{{ $stockSales > 0 ? 'negative' : '' }}">{{ number_format($stockSales,4,'.',',') }}</b></span>
                                                <span>Stok Setelah Sales<b>{{ number_format($stockAdjusted,4,'.',',') }}</b></span>
                                                <span>Proporsi Stok<b data-proportion>-</b></span>
                                                <span>Fisik Proporsional<b data-proportional-qty>-</b></span>
                                                <span>Selisih<b data-row-variance>-</b></span>
                                            </div>
                                            @if($stockSales > 0)
                                                <div class="ws-sales-invoice-list">
                                                    <strong>Sales Invoice {{ !empty($salesInfo['sales_date']) ? \Carbon\Carbon::parse($salesInfo['sales_date'])->format('d/m/Y') : '' }}</strong>
                                                    @foreach(($salesInfo['invoices'] ?? []) as $invoice)
                                                        <span>{{ $invoice['invoice_number'] ?? '-' }} <b>{{ number_format((float)($invoice['qty'] ?? 0),4,'.',',') }}</b></span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                            <div class="ws-allocation-summary ws-allocation-summary-sales">
                                <span>Snapshot Sistem <b>{{ number_format((float)$item->system_qty,4,'.',',') }}</b></span>
                                <span>Sales Invoice <b class="{{ $salesTotal > 0 ? 'negative' : '' }}">{{ number_format($salesTotal,4,'.',',') }}</b></span>
                                <span>Sistem Setelah Sales <b>{{ number_format($adjustedSystemTotal,4,'.',',') }}</b></span>
                                <span>Fisik Checker <b>{{ number_format((float)$item->physical_qty,4,'.',',') }}</b></span>
                                <span>Total Distribusi <b data-allocation-total>0</b></span>
                            </div>
                            <label class="ws-validation-note">Catatan Validasi<textarea name="validation_note" rows="2" placeholder="Opsional: catatan hasil validasi"></textarea></label>
                            <button class="btn primary" type="submit" data-validation-save>Simpan Validasi Proporsional</button>
                        </form>
                    @endif
                @else
                    <div class="ws-allocation-horizontal-scroll ws-snapshot-horizontal-scroll" aria-label="Snapshot stok per gudang">
                        <div class="ws-allocation-horizontal-track">
                            @foreach($item->stocks as $stock)
                                <article class="ws-allocation-card ws-snapshot-card">
                                    <div class="ws-allocation-card-head"><span>{{ $stock->warehouse?->source_database }}</span></div>
                                    <strong class="ws-allocation-warehouse-code">{{ $stock->warehouse?->warehouse_code }}</strong>
                                    <small class="ws-allocation-warehouse-name">{{ $stock->warehouse?->warehouse_name }}</small>
                                    <div class="ws-allocation-system-row"><span>Snapshot Stok</span><b>{{ number_format((float)$stock->system_qty,4,'.',',') }}</b></div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($period->isDraft())
                    <form method="POST" action="{{ route('warehouse.admin.items.destroy',[$period,$item]) }}" onsubmit="return confirm('Hapus baris item ini?')">@csrf @method('DELETE')<button class="btn small danger" type="submit">Hapus Item</button></form>
                @endif
            </article>
        @empty
            <div class="empty">Belum ada item pada periode ini.</div>
        @endforelse
    </div>

    {{ $items->links() }}
</section>

<div class="ws-actions">
    @if($period->isDraft())
        <form method="POST" action="{{ route('warehouse.admin.release',$period) }}" onsubmit="return confirm('Serahkan daftar ini ke Checker Gudang? Daftar database, gudang, dan item akan dikunci.')">@csrf<button class="btn primary" type="submit">Serahkan ke Checker</button></form>
    @elseif(!$period->isClosed())
        <form method="POST" action="{{ route('warehouse.admin.close',$period) }}" onsubmit="return confirm('Tutup periode ini?')">@csrf<button class="btn danger" type="submit">Tutup Periode</button></form>
    @endif
</div>
@endsection

@push('scripts')
@if($period->isDraft())
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const url=@json(route('warehouse.admin.items.available',$period));
    const body=document.getElementById('ws-stock-body');
    const search=document.getElementById('ws-item-search');
    const perPage=document.getElementById('ws-page-size');
    const selectPage=document.getElementById('ws-select-page');
    const selectedCount=document.getElementById('ws-selected-count');
    const selectedInputs=document.getElementById('ws-selected-inputs');
    const saveBtn=document.getElementById('ws-save-items');
    const info=document.getElementById('ws-stock-info');
    const pageLabel=document.getElementById('ws-page-label');
    const prev=document.getElementById('ws-prev-page');
    const next=document.getElementById('ws-next-page');
    const form=document.getElementById('ws-bulk-item-form');
    const selected=new Map();
    let currentRows=[]; let page=1; let lastPage=1; let timer=null; let loading=false;

    const escapeHtml=v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
    const formatQty=v=>Number(v||0).toLocaleString('id-ID',{minimumFractionDigits:0,maximumFractionDigits:4});

    function syncSelected(){
        selectedCount.textContent=selected.size; saveBtn.disabled=selected.size===0; selectedInputs.innerHTML='';
        selected.forEach((_,key)=>{const input=document.createElement('input');input.type='hidden';input.name='item_keys[]';input.value=key;selectedInputs.appendChild(input);});
        const keys=currentRows.map(i=>String(i.key));
        selectPage.checked=keys.length>0&&keys.every(key=>selected.has(key));
        selectPage.indeterminate=keys.some(key=>selected.has(key))&&!selectPage.checked;
    }

    function render(rows){
        currentRows=rows; body.innerHTML='';
        if(!rows.length){body.innerHTML='<tr><td colspan="5" class="empty">Tidak ada item stok gabungan yang cocok.</td></tr>';syncSelected();return;}
        rows.forEach(item=>{
            const key=String(item.key); const tr=document.createElement('tr'); if(selected.has(key))tr.classList.add('selected');
            const picked=item.selected_count>0?`<span class="ws-picked-badge">${item.selected_count}x</span>`:'<span class="muted">Belum</span>';
            tr.innerHTML=`<td class="ws-check-col"><input type="checkbox" class="ws-item-check" ${selected.has(key)?'checked':''}></td><td><strong>${escapeHtml(item.item_code)}</strong><small>${escapeHtml(item.item_name)}</small></td><td>${escapeHtml(item.uom_code)}</td><td class="num"><strong>${formatQty(item.system_qty)}</strong></td><td>${picked}</td>`;
            const checkbox=tr.querySelector('.ws-item-check');
            checkbox.addEventListener('change',()=>{if(checkbox.checked)selected.set(key,item);else selected.delete(key);tr.classList.toggle('selected',checkbox.checked);syncSelected();});
            tr.addEventListener('click',e=>{if(e.target===checkbox||e.target.closest('button,a,input,label'))return;checkbox.checked=!checkbox.checked;checkbox.dispatchEvent(new Event('change'));});
            body.appendChild(tr);
        });
        syncSelected();
    }

    async function load(targetPage=1){
        if(loading)return; loading=true; body.innerHTML='<tr><td colspan="5" class="empty">Memuat stok gabungan seluruh gudang...</td></tr>'; prev.disabled=true;next.disabled=true;
        try{
            const params=new URLSearchParams({page:String(targetPage),per_page:perPage.value});const q=search.value.trim();if(q)params.set('q',q);
            const response=await fetch(`${url}?${params.toString()}`,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});const json=await response.json();
            if(!response.ok)throw new Error(json?.message||'Gagal membaca stok gabungan.');
            page=json.meta?.page||1;lastPage=json.meta?.last_page||1;render(json.data||[]);
            info.textContent=`Menampilkan ${json.meta?.from||0}-${json.meta?.to||0} dari ${json.meta?.total||0} item unik dengan total stok gabungan > 0`;
            pageLabel.textContent=`Halaman ${page} / ${lastPage}`;prev.disabled=page<=1;next.disabled=page>=lastPage;
        }catch(e){currentRows=[];body.innerHTML=`<tr><td colspan="5" class="empty">${escapeHtml(e.message||'Gagal mengambil stok ERP.')}</td></tr>`;info.textContent='';pageLabel.textContent='';}
        finally{loading=false;}
    }

    selectPage.addEventListener('change',()=>{currentRows.forEach(item=>{const key=String(item.key);if(selectPage.checked)selected.set(key,item);else selected.delete(key);});render(currentRows);});
    search.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>load(1),300);});perPage.addEventListener('change',()=>load(1));prev.addEventListener('click',()=>page>1&&load(page-1));next.addEventListener('click',()=>page<lastPage&&load(page+1));
    form.addEventListener('submit',e=>{if(selected.size===0){e.preventDefault();return;}saveBtn.disabled=true;saveBtn.textContent=`Menyimpan ${selected.size} item...`;});
    load(1);
});
</script>
@endif
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const qty=v=>Number(v||0).toLocaleString('id-ID',{minimumFractionDigits:0,maximumFractionDigits:4});

    document.querySelectorAll('.ws-validation-form').forEach(form=>{
        const physical=Math.round(Number(form.dataset.physicalTotal||0)*10000)/10000;
        const rows=[...form.querySelectorAll('[data-stock-row]')];
        const eligible=rows.filter(row=>row.dataset.canAllocate==='1'&&Number(row.dataset.system||0)>0);
        const systemTotal=eligible.reduce((sum,row)=>sum+Number(row.dataset.system||0),0);
        let allocated=0;

        eligible.forEach((row,index)=>{
            const system=Number(row.dataset.system||0);
            const isLast=index===eligible.length-1;
            const value=isLast
                ? Math.round((physical-allocated)*10000)/10000
                : Math.round((physical*(system/systemTotal))*10000)/10000;
            allocated=Math.round((allocated+value)*10000)/10000;
            const proportion=systemTotal>0?(system/systemTotal)*100:0;
            const variance=value-system;
            row.querySelector('[data-proportion]').textContent=proportion.toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2})+'%';
            row.querySelector('[data-proportional-qty]').textContent=qty(value);
            const varianceEl=row.querySelector('[data-row-variance]');
            varianceEl.textContent=qty(variance);
            varianceEl.classList.toggle('negative',variance<-.0001);
            varianceEl.classList.toggle('positive',variance>.0001);
        });

        rows.filter(row=>!eligible.includes(row)).forEach(row=>{
            row.querySelector('[data-proportion]').textContent='0%';
            row.querySelector('[data-proportional-qty]').textContent='0';
            row.querySelector('[data-row-variance]').textContent=qty(-Number(row.dataset.system||0));
        });

        const totalEl=form.querySelector('[data-allocation-total]');
        if(totalEl)totalEl.textContent=qty(allocated);
    });
});
</script>
@endpush
