@extends('layouts.app')
@section('title','User Management')
@section('content')
@php
    $q = $search ?? '';
    $roleOptions = [
        'ADMIN' => 'ADMIN (Super Admin)',
        'ADMIN_GERAI' => 'ADMIN GERAI',
        'CHECKER_GERAI' => 'CHECKER GERAI',
        'ADMIN_GUDANG' => 'ADMIN GUDANG',
        'CHECKER_GUDANG' => 'CHECKER GUDANG',
        'CHECKER' => 'CHECKER (Legacy Stock Opname)',
        'GERAI' => 'GERAI (Legacy → Checker Gerai)',
    ];
    $databases = [\App\Models\StockOpnameCycle::DB_INGCO, \App\Models\StockOpnameCycle::DB_SMI];
@endphp

<div class="page-heading master-page-heading">
    <div>
        <h1>User Management</h1>
        <p>Atur akses Admin Gerai, Checker Gerai, Admin Gudang, Checker Gudang, dan assignment gudang lintas database.</p>
    </div>
    <button class="btn primary" type="button" id="user-create-open">+ Tambah User</button>
</div>

<section class="panel master-table-panel">
    <form method="GET" action="{{ route('admin.users.index') }}" class="table-filter-bar" id="user-filter-form">
        <label class="filter-field-wide"><span class="filter-label">Cari</span><input type="search" name="q" value="{{ $q }}" placeholder="Nama, username, database, gudang..."></label>
        <label class="filter-field"><span class="filter-label">Role</span><select name="role">
            <option value="">Semua</option>
            @foreach($roleOptions as $value => $label)<option value="{{ $value }}" @selected($role===$value)>{{ $label }}</option>@endforeach
        </select></label>
        <label class="filter-field"><span class="filter-label">Status</span><select name="status"><option value="">Semua</option><option value="active" @selected($status==='active')>Aktif</option><option value="inactive" @selected($status==='inactive')>Nonaktif</option></select></label>
        <label class="filter-field"><span class="filter-label">Baris</span><select name="per_page">@foreach([10,25,50,100] as $size)<option value="{{ $size }}" @selected($perPage===$size)>{{ $size }}</option>@endforeach</select></label>
        <div class="filter-actions"><button class="btn primary">Terapkan</button><a class="btn" href="{{ route('admin.users.index') }}">Reset</a></div>
    </form>

    <div class="table-wrap"><table class="master-table"><thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Assignment Gudang</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    @forelse($users as $user)
        @php
            $assignmentKeys = $user->warehouseAssignments->map(fn($a) => $a->source_database.'|'.$a->erp_warehouse_id)->values();
        @endphp
        <tr>
            <td><strong>{{ $user->name }}</strong></td>
            <td>{{ $user->username }}</td>
            <td><span class="role-badge {{ strtolower($user->role) }}">{{ $roleOptions[$user->role] ?? $user->role }}</span></td>
            <td>
                @if($user->warehouseAssignments->isNotEmpty())
                    <div class="user-assignment-summary">
                        @foreach($user->warehouseAssignments->take(4) as $assignment)
                            <span>{{ $assignment->source_database }} · {{ $assignment->warehouse_code }}</span>
                        @endforeach
                        @if($user->warehouseAssignments->count() > 4)<small>+{{ $user->warehouseAssignments->count()-4 }} gudang lain</small>@endif
                    </div>
                @else
                    <span class="muted">-</span>
                @endif
            </td>
            <td>{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</td>
            <td>
                <button type="button" class="btn small user-edit-btn"
                    data-user-edit
                    data-action="{{ route('admin.users.update',$user) }}"
                    data-name="{{ $user->name }}"
                    data-username="{{ $user->username }}"
                    data-role="{{ $user->role }}"
                    data-active="{{ $user->is_active ? '1':'0' }}"
                    data-assignments="{{ $assignmentKeys->toJson() }}">Edit</button>
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="empty">Belum ada user.</td></tr>
    @endforelse
    </tbody></table></div>
    @include('admin.partials.table-pagination',['paginator'=>$users])
</section>

@php
    $assignmentPanels = function() use ($databases) {
        return $databases;
    };
@endphp

<div class="user-modal" id="user-create-modal" hidden>
    <div class="user-modal-backdrop" data-create-close></div>
    <div class="user-modal-dialog user-modal-dialog-wide">
        <div class="user-modal-head"><div><h2>Tambah User</h2><p>User Gerai dapat di-assign ke beberapa gudang, termasuk lintas AS_INGCO dan AS_SMI.</p></div><button type="button" class="user-modal-close" data-create-close>×</button></div>
        <form method="POST" action="{{ route('admin.users.store') }}" class="stack-form gerai-user-form">@csrf
            <div class="grid-2"><label>Nama<input name="name" required></label><label>Username<input name="username" required></label></div>
            <div class="grid-2"><label>Password<input type="password" name="password" minlength="6" required></label><label>Role<select name="role" data-role-select>@foreach($roleOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label></div>
            <div class="multi-warehouse-assignment" data-gerai-assignment hidden>
                <div class="multi-warehouse-assignment-head"><div><strong>Assignment Gudang Gerai</strong><small>Pilih satu atau lebih gudang dari satu atau dua database.</small></div><span data-assignment-count>0 gudang dipilih</span></div>
                <div class="multi-warehouse-db-grid">
                    @foreach($databases as $db)
                        <section class="multi-warehouse-db-card" data-assignment-db="{{ $db }}">
                            <header><strong>{{ $db }}</strong><small data-db-status>Belum dimuat</small></header>
                            <input type="search" class="multi-warehouse-search" data-db-search placeholder="Cari kode / nama gudang...">
                            <div class="multi-warehouse-list" data-db-list><div class="empty compact">Gudang akan dimuat otomatis.</div></div>
                        </section>
                    @endforeach
                </div>
            </div>
            <div class="user-modal-actions"><button type="button" class="btn" data-create-close>Batal</button><button class="btn primary">Simpan User</button></div>
        </form>
    </div>
</div>

<div class="user-modal" id="user-edit-modal" hidden>
    <div class="user-modal-backdrop" data-edit-close></div>
    <div class="user-modal-dialog user-modal-dialog-wide">
        <div class="user-modal-head"><div><h2>Edit User</h2><p>Perubahan assignment berlaku untuk pemilihan periode Gerai berikutnya.</p></div><button type="button" class="user-modal-close" data-edit-close>×</button></div>
        <form method="POST" id="user-edit-form" class="stack-form gerai-user-form">@csrf @method('PUT')
            <div class="grid-2"><label>Nama<input id="edit-name" name="name" required></label><label>Username<input id="edit-username" name="username" required></label></div>
            <div class="grid-2"><label>Role<select id="edit-role" name="role" data-role-select>@foreach($roleOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label><label>Password Baru<input type="password" name="password" minlength="6" placeholder="Kosong = tidak berubah"></label></div>
            <label class="check"><input id="edit-active" type="checkbox" name="is_active" value="1"> Akun Aktif</label>
            <div class="multi-warehouse-assignment" data-gerai-assignment hidden>
                <div class="multi-warehouse-assignment-head"><div><strong>Assignment Gudang Gerai</strong><small>Boleh pilih beberapa gudang dari AS_INGCO dan AS_SMI sekaligus.</small></div><span data-assignment-count>0 gudang dipilih</span></div>
                <div class="multi-warehouse-db-grid">
                    @foreach($databases as $db)
                        <section class="multi-warehouse-db-card" data-assignment-db="{{ $db }}">
                            <header><strong>{{ $db }}</strong><small data-db-status>Belum dimuat</small></header>
                            <input type="search" class="multi-warehouse-search" data-db-search placeholder="Cari kode / nama gudang...">
                            <div class="multi-warehouse-list" data-db-list><div class="empty compact">Gudang akan dimuat otomatis.</div></div>
                        </section>
                    @endforeach
                </div>
            </div>
            <div class="user-modal-actions"><button type="button" class="btn" data-edit-close>Batal</button><button class="btn primary">Simpan Perubahan</button></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const warehouseUrl = @json(route('admin.erp.warehouses'));
    const geraiRoles = new Set(['ADMIN_GERAI','CHECKER_GERAI','GERAI']);
    const cache = new Map();

    async function loadDatabase(db) {
        if (cache.has(db)) return cache.get(db);
        const response = await fetch(`${warehouseUrl}?source_database=${encodeURIComponent(db)}`, {headers:{Accept:'application/json'}});
        if (!response.ok) throw new Error(`Gagal membaca gudang ${db}`);
        const json = await response.json();
        const rows = json.data || [];
        cache.set(db, rows);
        return rows;
    }

    function selectedValues(form) {
        return new Set(Array.from(form.querySelectorAll('input[name="warehouse_assignments[]"]:checked')).map(x => x.value));
    }

    function refreshCount(form) {
        const el = form.querySelector('[data-assignment-count]');
        if (el) el.textContent = `${selectedValues(form).size} gudang dipilih`;
    }

    function filterPanel(panel) {
        const term = (panel.querySelector('[data-db-search]')?.value || '').trim().toLowerCase();
        panel.querySelectorAll('[data-warehouse-option]').forEach(row => {
            const haystack = (row.dataset.search || '').toLowerCase();
            row.hidden = Boolean(term) && !haystack.includes(term);
        });
    }

    async function renderAssignments(form, selected = new Set()) {
        const role = form.querySelector('[data-role-select]')?.value;
        const box = form.querySelector('[data-gerai-assignment]');
        if (!box) return;
        box.hidden = !geraiRoles.has(role);
        if (box.hidden) return;

        const panels = Array.from(box.querySelectorAll('[data-assignment-db]'));
        await Promise.all(panels.map(async panel => {
            const db = panel.dataset.assignmentDb;
            const list = panel.querySelector('[data-db-list]');
            const status = panel.querySelector('[data-db-status]');
            status.textContent = 'Memuat...';
            try {
                const rows = await loadDatabase(db);
                list.innerHTML = '';
                rows.forEach(w => {
                    const value = `${db}|${w.warehouse_id}`;
                    const label = document.createElement('label');
                    label.className = 'multi-warehouse-option';
                    label.dataset.warehouseOption = '1';
                    label.dataset.search = `${w.warehouse_code} ${w.warehouse_name}`;
                    label.innerHTML = `<input type="checkbox" name="warehouse_assignments[]" value="${value}"><span><strong></strong><small></small></span>`;
                    label.querySelector('strong').textContent = w.warehouse_code;
                    label.querySelector('small').textContent = w.warehouse_name;
                    label.querySelector('input').checked = selected.has(value);
                    label.querySelector('input').addEventListener('change', () => refreshCount(form));
                    list.appendChild(label);
                });
                status.textContent = `${rows.length} gudang`;
                filterPanel(panel);
            } catch (error) {
                list.innerHTML = `<div class="empty compact">${error.message}</div>`;
                status.textContent = 'Gagal dimuat';
            }
        }));
        refreshCount(form);
    }

    document.querySelectorAll('.gerai-user-form').forEach(form => {
        form.querySelector('[data-role-select]')?.addEventListener('change', () => renderAssignments(form, selectedValues(form)));
        form.querySelectorAll('[data-db-search]').forEach(input => input.addEventListener('input', () => filterPanel(input.closest('[data-assignment-db]'))));
    });

    const cm = document.getElementById('user-create-modal');
    const em = document.getElementById('user-edit-modal');
    const ef = document.getElementById('user-edit-form');
    const cf = cm?.querySelector('form');
    const show = modal => { modal.hidden = false; document.body.classList.add('modal-open'); };
    const hide = modal => { modal.hidden = true; document.body.classList.remove('modal-open'); };

    document.getElementById('user-create-open')?.addEventListener('click', async () => {
        show(cm);
        await renderAssignments(cf, new Set());
    });
    cm?.querySelectorAll('[data-create-close]').forEach(x => x.addEventListener('click', () => hide(cm)));
    em?.querySelectorAll('[data-edit-close]').forEach(x => x.addEventListener('click', () => hide(em)));

    document.querySelectorAll('[data-user-edit]').forEach(btn => btn.addEventListener('click', async () => {
        ef.action = btn.dataset.action;
        document.getElementById('edit-name').value = btn.dataset.name || '';
        document.getElementById('edit-username').value = btn.dataset.username || '';
        document.getElementById('edit-role').value = btn.dataset.role || 'CHECKER_GERAI';
        document.getElementById('edit-active').checked = btn.dataset.active === '1';
        let selected = [];
        try { selected = JSON.parse(btn.dataset.assignments || '[]'); } catch (_) {}
        show(em);
        await renderAssignments(ef, new Set(selected));
    }));
});
</script>
@endpush
