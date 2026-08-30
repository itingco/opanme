@extends('layouts.app')
@section('title','User Management')
@section('content')
@php
    $sortUrl = function (string $column) use ($sort, $direction) {
        $params = request()->except('page');
        $params['sort'] = $column;
        $params['direction'] = $sort === $column && $direction === 'asc' ? 'desc' : 'asc';
        return route('admin.users.index', $params);
    };
    $sortMark = fn (string $column) => $sort === $column ? ($direction === 'asc' ? '↑' : '↓') : '↕';
    $hasFilters = $q !== '' || $role !== '' || $status !== '';
@endphp

<div class="page-heading master-page-heading">
    <div>
        <div class="heading-eyebrow">Master Data</div>
        <h1>User Management</h1>
        <p>Kelola akun, role, dan status akses pengguna Stock Opname.</p>
    </div>
    <button class="btn primary" type="button" id="user-create-open">
        <span class="btn-icon">+</span> Tambah User
    </button>
</div>

<section class="panel master-table-panel">
    <div class="master-table-head">
        <div>
            <h2>Daftar User</h2>
            <p>{{ number_format($users->total()) }} akun ditemukan</p>
        </div>
        @if($hasFilters)
            <span class="filter-active-badge">Filter aktif</span>
        @endif
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="table-filter-bar" id="user-filter-form">
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="direction" value="{{ $direction }}">

        <label class="filter-search filter-field-wide">
            <span class="filter-label">Cari User</span>
            <div class="filter-input-with-icon">
                <span aria-hidden="true">⌕</span>
                <input type="search" name="q" value="{{ $q }}" placeholder="Nama atau username..." autocomplete="off">
            </div>
        </label>

        <label class="filter-field">
            <span class="filter-label">Role</span>
            <select name="role">
                <option value="">Semua Role</option>
                <option value="CHECKER" @selected($role === 'CHECKER')>CHECKER</option>
                <option value="ADMIN" @selected($role === 'ADMIN')>ADMIN</option>
            </select>
        </label>

        <label class="filter-field">
            <span class="filter-label">Status</span>
            <select name="status">
                <option value="">Semua Status</option>
                <option value="active" @selected($status === 'active')>Aktif</option>
                <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
            </select>
        </label>

        <label class="filter-field filter-per-page">
            <span class="filter-label">Baris</span>
            <select name="per_page">
                @foreach([10,25,50,100] as $size)
                    <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </label>

        <div class="filter-actions">
            <button class="btn primary" type="submit">Terapkan</button>
            <a class="btn filter-reset-btn {{ $hasFilters ? '' : 'muted-button' }}" href="{{ route('admin.users.index') }}">Reset</a>
        </div>
    </form>

    <div class="table-wrap master-table-wrap">
        <table class="master-table user-table">
            <thead>
                <tr>
                    <th class="row-number-col">No</th>
                    <th>
                        <a class="sortable-th" data-sort="name" href="{{ $sortUrl('name') }}">Nama <span>{{ $sortMark('name') }}</span></a>
                    </th>
                    <th>
                        <a class="sortable-th" data-sort="username" href="{{ $sortUrl('username') }}">Username <span>{{ $sortMark('username') }}</span></a>
                    </th>
                    <th>
                        <a class="sortable-th" data-sort="role" href="{{ $sortUrl('role') }}">Role <span>{{ $sortMark('role') }}</span></a>
                    </th>
                    <th>
                        <a class="sortable-th" data-sort="status" href="{{ $sortUrl('status') }}">Status <span>{{ $sortMark('status') }}</span></a>
                    </th>
                    <th class="action-col">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td data-label="No" class="row-number">{{ number_format(($users->firstItem() ?? 1) + $loop->index) }}</td>
                    <td data-label="Nama">
                        <div class="user-identity">
                            <span class="user-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                            <span>
                                <strong>{{ $user->name }}</strong>
                                <small>User ID #{{ $user->id }}</small>
                            </span>
                        </div>
                    </td>
                    <td data-label="Username"><span class="username-chip">{{ $user->username }}</span></td>
                    <td data-label="Role"><span class="role-badge {{ strtolower($user->role) }}">{{ $user->role }}</span></td>
                    <td data-label="Status">
                        <span class="user-status-badge {{ $user->is_active ? 'active' : 'inactive' }}">
                            <span class="status-dot"></span>{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td data-label="Aksi" class="action-cell">
                        <button
                            type="button"
                            class="btn small user-edit-btn"
                            data-user-edit
                            data-action="{{ route('admin.users.update', $user) }}"
                            data-name="{{ $user->name }}"
                            data-username="{{ $user->username }}"
                            data-role="{{ $user->role }}"
                            data-active="{{ $user->is_active ? '1' : '0' }}"
                        >Edit</button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty table-empty-state">
                        <strong>{{ $hasFilters ? 'User tidak ditemukan' : 'Belum ada user' }}</strong>
                        <span>{{ $hasFilters ? 'Coba ubah kata kunci atau reset filter.' : 'Tambahkan user pertama untuk mulai memberikan akses.' }}</span>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.table-pagination', ['paginator' => $users])
</section>

<div class="user-modal" id="user-create-modal" hidden aria-hidden="true">
    <div class="user-modal-backdrop" data-create-close></div>
    <div class="user-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="user-create-title">
        <div class="user-modal-head">
            <div>
                <span class="modal-eyebrow">Akun Baru</span>
                <h2 id="user-create-title">Tambah User</h2>
                <p>Buat akun Admin atau Checker baru.</p>
            </div>
            <button type="button" class="user-modal-close" data-create-close aria-label="Tutup modal">×</button>
        </div>
        <form method="POST" action="{{ route('admin.users.store') }}" class="stack-form">
            @csrf
            <label>Nama<input name="name" value="{{ old('name') }}" placeholder="Nama lengkap" required autocomplete="name"></label>
            <label>Username<input name="username" value="{{ old('username') }}" placeholder="Contoh: checker01" required autocomplete="username"></label>
            <label>Password<input type="password" name="password" minlength="6" placeholder="Minimal 6 karakter" required autocomplete="new-password"></label>
            <label>Role
                <select name="role">
                    <option value="CHECKER" @selected(old('role', 'CHECKER') === 'CHECKER')>CHECKER</option>
                    <option value="ADMIN" @selected(old('role') === 'ADMIN')>ADMIN</option>
                </select>
            </label>
            <div class="user-modal-actions">
                <button type="button" class="btn" data-create-close>Batal</button>
                <button type="submit" class="btn primary">Simpan User</button>
            </div>
        </form>
    </div>
</div>

<div class="user-modal" id="user-edit-modal" hidden aria-hidden="true">
    <div class="user-modal-backdrop" data-edit-close></div>
    <div class="user-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="user-edit-title">
        <div class="user-modal-head">
            <div>
                <span class="modal-eyebrow">Pengaturan Akun</span>
                <h2 id="user-edit-title">Edit User</h2>
                <p>Perbarui profil, role, password, atau status akun.</p>
            </div>
            <button type="button" class="user-modal-close" data-edit-close aria-label="Tutup modal">×</button>
        </div>

        <form method="POST" id="user-edit-form" class="stack-form user-edit-form">
            @csrf
            @method('PUT')
            <label>Nama<input id="edit-name" name="name" required autocomplete="name"></label>
            <label>Username<input id="edit-username" name="username" required autocomplete="username"></label>
            <label>Role
                <select id="edit-role" name="role">
                    <option value="CHECKER">CHECKER</option>
                    <option value="ADMIN">ADMIN</option>
                </select>
            </label>
            <label>Password Baru
                <input id="edit-password" type="password" name="password" minlength="6" placeholder="Kosong = password tidak berubah" autocomplete="new-password">
                <small class="field-help">Biarkan kosong jika tidak ingin mengganti password.</small>
            </label>
            <label class="user-active-toggle">
                <input id="edit-active" type="checkbox" name="is_active" value="1">
                <span><strong>Akun Aktif</strong><small>User dapat login dan menggunakan aplikasi.</small></span>
            </label>
            <div class="user-modal-actions">
                <button type="button" class="btn" data-edit-close>Batal</button>
                <button type="submit" class="btn primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const createModal = document.getElementById('user-create-modal');
    const editModal = document.getElementById('user-edit-modal');
    const editForm = document.getElementById('user-edit-form');
    const nameInput = document.getElementById('edit-name');
    const usernameInput = document.getElementById('edit-username');
    const roleInput = document.getElementById('edit-role');
    const passwordInput = document.getElementById('edit-password');
    const activeInput = document.getElementById('edit-active');
    let lastTrigger = null;

    function showModal(modal, trigger) {
        lastTrigger = trigger || null;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function hideModal(modal) {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        lastTrigger?.focus();
    }

    document.getElementById('user-create-open')?.addEventListener('click', event => showModal(createModal, event.currentTarget));
    createModal.querySelectorAll('[data-create-close]').forEach(el => el.addEventListener('click', () => hideModal(createModal)));

    document.querySelectorAll('[data-user-edit]').forEach(button => {
        button.addEventListener('click', () => {
            editForm.action = button.dataset.action;
            nameInput.value = button.dataset.name || '';
            usernameInput.value = button.dataset.username || '';
            roleInput.value = button.dataset.role || 'CHECKER';
            activeInput.checked = button.dataset.active === '1';
            passwordInput.value = '';
            showModal(editModal, button);
            setTimeout(() => nameInput.focus(), 20);
        });
    });
    editModal.querySelectorAll('[data-edit-close]').forEach(el => el.addEventListener('click', () => hideModal(editModal)));

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        if (!createModal.hidden) hideModal(createModal);
        if (!editModal.hidden) hideModal(editModal);
    });

    document.querySelectorAll('#user-filter-form select').forEach(select => {
        select.addEventListener('change', () => document.getElementById('user-filter-form').requestSubmit());
    });
});
</script>
@endpush
