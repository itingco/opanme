@extends('layouts.app')
@section('title','Admin Gerai')
@section('content')
@php
    $assignmentCollection = collect($assignments);
    $checkersPayload = $checkers->mapWithKeys(function($checker) {
        return [$checker->id => $checker->warehouseAssignments->map(fn($a) => $a->source_database.'|'.$a->erp_warehouse_id)->values()->all()];
    });
@endphp
<div class="page-heading">
    <div>
        <h1>Admin Sampling Gerai</h1>
        <p>Buat cycle dari gudang yang di-assign ke Anda, lalu serahkan ke Checker Gerai yang memiliki akses gudang yang sama.</p>
    </div>
</div>

<div class="split-grid gerai-admin-grid">
    <section class="panel">
        <div class="panel-head"><h2>Buat Cycle Gerai</h2><span class="status open">{{ $assignmentCollection->count() }} gudang akses</span></div>
        @if($assignmentCollection->isEmpty())
            <div class="alert danger">User ini belum memiliki assignment gudang. Minta Super Admin menambahkan gudang di User Management.</div>
        @else
            <form method="POST" action="{{ route('gerai.admin.create') }}" class="stack-form" id="gerai-admin-cycle-form">@csrf
                <label>Gudang
                    <select name="warehouse_key" id="gerai-admin-warehouse" required>
                        <option value="">Pilih gudang</option>
                        @foreach($assignmentCollection->groupBy('source_database') as $database => $rows)
                            <optgroup label="{{ $database }}">
                                @foreach($rows as $warehouse)
                                    <option value="{{ $database }}|{{ $warehouse['warehouse_id'] }}">{{ $warehouse['warehouse_code'] }} · {{ $warehouse['warehouse_name'] }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </label>
                <label>Checker Gerai
                    <select name="assigned_checker_id" id="gerai-admin-checker" required disabled>
                        <option value="">Pilih gudang terlebih dahulu</option>
                        @foreach($checkers as $checker)
                            <option value="{{ $checker->id }}">{{ $checker->name }} · {{ $checker->username }}</option>
                        @endforeach
                    </select>
                    <small id="gerai-checker-help">Checker otomatis difilter berdasarkan assignment gudang.</small>
                </label>
                <label>Lokasi / Rak
                    <input name="location" maxlength="255" placeholder="Contoh: Rak A, Etalase Depan, Gudang Belakang" required>
                </label>
                <button class="btn primary" type="submit">Buat & Assign Cycle</button>
            </form>
        @endif
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Assignment Gudang Saya</h2><small class="muted">Bisa lintas database.</small></div>
        <div class="gerai-assignment-list">
            @forelse($assignmentCollection as $warehouse)
                <div class="gerai-assignment-row">
                    <span>{{ $warehouse['source_database'] }}</span>
                    <div><strong>{{ $warehouse['warehouse_code'] }}</strong><small>{{ $warehouse['warehouse_name'] }}</small></div>
                </div>
            @empty
                <div class="empty compact">Belum ada assignment gudang.</div>
            @endforelse
        </div>
    </section>
</div>

<section class="panel">
    <div class="panel-head"><h2>Cycle Gerai</h2><small>{{ $cycles->total() }} cycle</small></div>
    <div class="table-wrap"><table><thead><tr><th>Cycle</th><th>Mulai</th><th>Database / Gudang</th><th>Checker</th><th>Lokasi</th><th>Status</th><th class="num">Item Dicek</th></tr></thead><tbody>
    @forelse($cycles as $cycle)
        <tr>
            <td><strong>{{ $cycle->cycle_no }}</strong></td>
            <td>{{ $cycle->started_at?->format('d/m/Y H:i') }}</td>
            <td><strong>{{ $cycle->source_database }} · {{ $cycle->warehouse_code }}</strong><small>{{ $cycle->warehouse_name }}</small></td>
            <td>{{ $cycle->checker?->name ?? '-' }}</td>
            <td>{{ $cycle->location }}</td>
            <td><span class="status {{ strtolower($cycle->status) }}">{{ $cycle->status }}</span></td>
            <td class="num">{{ number_format($cycle->checks_count) }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="empty">Belum ada cycle Gerai.</td></tr>
    @endforelse
    </tbody></table></div>
    {{ $cycles->links() }}
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const warehouse = document.getElementById('gerai-admin-warehouse');
    const checker = document.getElementById('gerai-admin-checker');
    const help = document.getElementById('gerai-checker-help');
    const access = @json($checkersPayload);
    if (!warehouse || !checker) return;

    const allOptions = Array.from(checker.querySelectorAll('option[value]')).map(o => ({value:o.value,text:o.textContent}));
    function refresh() {
        const key = warehouse.value;
        checker.innerHTML = '';
        if (!key) {
            checker.disabled = true;
            checker.innerHTML = '<option value="">Pilih gudang terlebih dahulu</option>';
            help.textContent = 'Checker otomatis difilter berdasarkan assignment gudang.';
            return;
        }
        const eligible = allOptions.filter(o => Array.isArray(access[o.value]) && access[o.value].includes(key));
        checker.disabled = eligible.length === 0;
        const first = document.createElement('option'); first.value=''; first.textContent = eligible.length ? 'Pilih checker' : 'Tidak ada checker dengan akses gudang ini'; checker.appendChild(first);
        eligible.forEach(row => { const o=document.createElement('option'); o.value=row.value; o.textContent=row.text; checker.appendChild(o); });
        help.textContent = `${eligible.length} Checker Gerai memiliki assignment ke gudang ini.`;
    }
    warehouse.addEventListener('change', refresh);
    refresh();
});
</script>
@endpush
