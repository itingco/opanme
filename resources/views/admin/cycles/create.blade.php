@extends('layouts.app')
@section('title','Buat Cycle')
@section('content')
<div class="page-heading"><div><h1>Buat Cycle Baru</h1><p>Pilih database dan warehouse yang berada dalam area fisik stock opname.</p></div></div>
<section class="panel narrow">
<form method="POST" action="{{ route('admin.cycles.store') }}" class="stack-form" id="cycle-form">@csrf
<label>Database ERP<select name="source_database" id="cycle-db" required><option value="">Pilih database</option>@foreach($databases as $db)<option value="{{ $db }}" @selected(old('source_database')===$db)>{{ $db }}</option>@endforeach<option value="COMBINED" @selected(old('source_database')==='COMBINED')>COMBINE Database / Gudang</option></select></label>
<div id="combine-options" hidden>
    <div class="label-title">Database yang ingin digabung</div>
    <div style="display:flex;gap:18px;flex-wrap:wrap;margin:8px 0">
        @foreach($databases as $db)
        <label style="display:flex;gap:7px;align-items:center"><input type="checkbox" class="combine-db" value="{{ $db }}" @checked(!old('combined_warehouses') || collect(old('combined_warehouses',[]))->contains(fn($key)=>str_starts_with((string)$key,$db.':')))>{{ $db }}</label>
        @endforeach
    </div>
    <p class="muted">Pilih minimal dua gudang. Checker menghitung fisik satu kali, kemudian sistem membagi hasil berdasarkan proporsi stok opening masing-masing gudang.</p>
</div>
<label>Tanggal Cutoff<input type="date" name="cutoff_date" value="{{ old('cutoff_date', now()->format('Y-m-d')) }}" required></label>
<div>
    <div class="label-title">Warehouse aktif</div>
    <div class="cycle-warehouse-search">
        <label for="warehouse-search" class="cycle-search-label">Cari gudang</label>
        <div class="cycle-search-row">
            <input id="warehouse-search" type="search" placeholder="Cari kode, nama gudang atau database..." autocomplete="off" disabled>
            <button id="warehouse-search-clear" class="btn small" type="button" disabled>Reset</button>
        </div>
        <small id="warehouse-search-count" aria-live="polite"></small>
    </div>
    <div id="warehouse-list" class="warehouse-picker"><div class="empty compact">Pilih database untuk mengambil warehouse.</div></div>
</div>
<style>
    #cycle-form .cycle-warehouse-search {margin:8px 0 10px;}
    #cycle-form .cycle-search-label {display:block;margin-bottom:5px;font-size:12px;}
    #cycle-form .cycle-search-row {display:flex;align-items:center;gap:8px;}
    #cycle-form #warehouse-search {flex:1;min-width:0;width:100%;}
    #cycle-form #warehouse-search-count {display:block;margin-top:6px;color:#64748b;}
    #cycle-form #warehouse-list {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;align-items:stretch;}
    #cycle-form #warehouse-list .cycle-db-heading,
    #cycle-form #warehouse-list .cycle-db-empty,
    #cycle-form #warehouse-list .cycle-search-empty,
    #cycle-form #warehouse-list > .empty,
    #cycle-form #warehouse-list > .danger-lite {grid-column:1/-1;}
    #cycle-form #warehouse-list .cycle-db-heading {margin:6px 0 0;padding:5px 0;font-weight:700;}
    #cycle-form #warehouse-list .warehouse-option {display:flex;flex-direction:row;align-items:center;justify-content:flex-start;gap:10px;min-width:0;min-height:60px;padding:10px;cursor:pointer;}
    #cycle-form #warehouse-list .warehouse-option input[type=checkbox],
    #cycle-form #combine-options input[type=checkbox] {width:18px;height:18px;min-width:18px;min-height:18px;flex:0 0 18px;margin:0;appearance:auto;accent-color:#2563eb;}
    #cycle-form #warehouse-list .cycle-wh-copy {display:block;flex:1;min-width:0;overflow-wrap:anywhere;}
    #cycle-form #warehouse-list .cycle-wh-title {display:flex;flex-wrap:wrap;align-items:center;gap:5px 8px;}
    #cycle-form #warehouse-list .cycle-wh-db {display:inline-block;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:700;line-height:1.4;background:#e0e7ff;color:#3730a3;}
    #cycle-form #warehouse-list .cycle-wh-db[data-db="AS_SMI"] {background:#dcfce7;color:#166534;}
    #cycle-form #warehouse-list .cycle-wh-name {display:block;margin-top:3px;}
    #cycle-form #warehouse-list [hidden] {display:none!important;}
    @media(max-width:520px) {#cycle-form #warehouse-list {grid-template-columns:minmax(0,1fr);}}
</style>
<div id="cycle-selection-count" aria-live="polite"></div>
<button class="btn primary full" id="cycle-submit" type="submit" disabled>Buat Cycle &amp; Lanjut Assignment</button>
</form>
</section>
@endsection
@push('scripts')
@php
    $oldCycleDatabase = (string)old('source_database','');
    $oldCycleWarehouseIds = array_map('strval',(array)old('warehouse_ids',[]));
    $oldCombinedWarehouseIds = array_map('strval',(array)old('combined_warehouses',[]));
@endphp
<script>
(() => {
    const select = document.getElementById('cycle-db');
    const list = document.getElementById('warehouse-list');
    const submit = document.getElementById('cycle-submit');
    const form = document.getElementById('cycle-form');
    const options = document.getElementById('combine-options');
    const counter = document.getElementById('cycle-selection-count');
    const search = document.getElementById('warehouse-search');
    const clear = document.getElementById('warehouse-search-clear');
    const searchCount = document.getElementById('warehouse-search-count');
    const endpoint = @json(auth()->user()->isAdminGerai() ? route('gerai.admin.warehouses') : route('admin.erp.warehouses'));
    const oldSingle = new Map([[@json($oldCycleDatabase), new Set(@json($oldCycleWarehouseIds))]]);
    const oldCombined = new Set(@json($oldCombinedWarehouseIds));
    let controller, generation = 0, loaded = false;
    const combined = () => select.value === 'COMBINED';
    const inputs = () => [...list.querySelectorAll('input[type="checkbox"]')];
    function showMessage(message, isError = false, reference = '') {
        list.replaceChildren();
        const panel = document.createElement('div');
        panel.className = isError ? 'danger-lite' : 'empty compact';
        panel.style.cssText = 'padding:14px;line-height:1.55;';
        panel.textContent = message + (reference ? ` (Kode error: ${reference})` : '');
        if (isError) {
            const retry = document.createElement('button'); retry.className = 'btn small'; retry.type = 'button';
            retry.textContent = 'Coba Lagi'; retry.style.display = 'block'; retry.addEventListener('click',loadWarehouses); panel.append(retry);
        }
        list.append(panel);
    }
    function applySearch() {
        const terms = search.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
        const cards = [...list.querySelectorAll('.warehouse-option')];
        const groups = new Set();
        let visible = 0;
        for (const card of cards) {
            const match = terms.every(term => card.dataset.search.includes(term));
            card.hidden = !match;
            if (match) { visible++; groups.add(card.dataset.db); }
        }
        for (const heading of list.querySelectorAll('.cycle-db-heading')) {
            heading.hidden = terms.length > 0 && !groups.has(heading.dataset.db);
        }
        for (const empty of list.querySelectorAll('.cycle-db-empty')) empty.hidden = terms.length > 0;
        const noResults = list.querySelector('.cycle-search-empty');
        if (noResults) noResults.hidden = !loaded || !terms.length || visible > 0;
        const hiddenSelected = cards.filter(card => card.hidden && card.querySelector('input').checked).length;
        searchCount.textContent = loaded ? `${visible} dari ${cards.length} gudang ditampilkan` + (hiddenSelected ? ` · ${hiddenSelected} gudang terpilih di luar hasil pencarian` : '') : '';
        clear.disabled = !loaded || search.value.length === 0;
    }
    function syncSubmit() {
        const count = inputs().filter(i=>i.checked).length;
        submit.disabled = !loaded || count < (combined()?2:1);
        counter.textContent = count ? `${count} gudang dipilih` : '';
        applySearch();
    }
    async function fetchDb(db, signal) {
        const url = new URL(endpoint, location.origin); url.searchParams.set('source_database',db);
        const response = await fetch(url,{headers:{'Accept':'application/json'},credentials:'same-origin',signal});
        const json = await response.json().catch(()=>({}));
        if (!response.ok || !Array.isArray(json.data)) throw Object.assign(new Error(`${db}: ${json.message || 'Gagal mengambil warehouse (HTTP '+response.status+')'}`),{reference:json.reference});
        return {db, warehouses:json.data};
    }
    async function loadWarehouses() {
        controller?.abort(); const current = ++generation; loaded=false; submit.disabled=true; counter.textContent='';
        options.hidden = !combined();
        search.disabled=true; clear.disabled=true; searchCount.textContent='';
        const mode = select.value;
        const dbs = combined() ? [...options.querySelectorAll('.combine-db:checked')].map(i=>i.value) : (mode?[mode]:[]);
        if (!dbs.length) { showMessage('Pilih database untuk mengambil warehouse.'); return; }
        showMessage('Mengambil warehouse dari ERP...'); controller = new AbortController();
        try {
            const result = await Promise.all(dbs.map(db=>fetchDb(db,controller.signal)));
            if (current!==generation) return;
            list.replaceChildren();
            if (mode!=='COMBINED' && !oldSingle.has(mode)) oldSingle.set(mode,new Set());
            const remembered = mode==='COMBINED'?oldCombined:oldSingle.get(mode);
            for (const {db,warehouses} of result) {
                const heading=document.createElement('div'); heading.className='label-title cycle-db-heading'; heading.dataset.db=db; heading.textContent=db; list.append(heading);
                if (!warehouses.length) {const empty=document.createElement('small');empty.className='cycle-db-empty';empty.textContent='Tidak ada warehouse aktif.';list.append(empty);}
                for (const warehouse of warehouses) {
                    const label=document.createElement('label'); label.className='warehouse-option';
                    label.dataset.db=db;
                    label.dataset.search=[warehouse.warehouse_code??'',warehouse.warehouse_name??'',db,db.replace(/^AS_/, '')].join(' ').toLowerCase();
                    const checkbox=document.createElement('input'); checkbox.type='checkbox';
                    checkbox.name=mode==='COMBINED'?'combined_warehouses[]':'warehouse_ids[]';
                    checkbox.value=mode==='COMBINED'?`${db}:${warehouse.warehouse_id}`:String(warehouse.warehouse_id);
                    checkbox.checked=remembered.has(checkbox.value);
                    checkbox.addEventListener('change',()=>{if(checkbox.checked)remembered.add(checkbox.value);else remembered.delete(checkbox.value);syncSubmit();});
                    const span=document.createElement('span'), title=document.createElement('span'), strong=document.createElement('strong'), small=document.createElement('small'), badge=document.createElement('span');
                    span.className='cycle-wh-copy'; title.className='cycle-wh-title'; small.className='cycle-wh-name';
                    strong.textContent=String(warehouse.warehouse_code??''); small.textContent=String(warehouse.warehouse_name??'');
                    badge.className='cycle-wh-db'; badge.dataset.db=db; badge.textContent=db;
                    title.append(strong,badge); span.append(title,small); label.append(checkbox,span); list.append(label);
                }
            }
            const noResults=document.createElement('div'); noResults.className='empty compact cycle-search-empty';
            noResults.textContent='Tidak ada gudang yang cocok dengan pencarian.'; noResults.hidden=true; list.append(noResults);
            loaded=true;search.disabled=false;syncSubmit();
        } catch(error) {if(error.name==='AbortError'||current!==generation)return;showMessage(error.message,true,error.reference||'');}
    }
    search.addEventListener('input',applySearch);
    clear.addEventListener('click',()=>{search.value='';applySearch();search.focus();});
    search.addEventListener('keydown',event=>{if(event.key==='Enter')event.preventDefault();});
    select.addEventListener('change',()=>{search.value='';loadWarehouses();});
    options.querySelectorAll('.combine-db').forEach(i=>i.addEventListener('change',loadWarehouses));
    form.addEventListener('submit',event=>{syncSubmit();if(submit.disabled)event.preventDefault();});
    if(select.value)loadWarehouses();
})();
</script>
@endpush
