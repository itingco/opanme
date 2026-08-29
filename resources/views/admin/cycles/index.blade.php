@extends('layouts.app')
@section('title','Cycle Stock Opname')
@section('content')
<div class="page-heading"><div><h1>Cycle Stock Opname</h1><p>Satu cycle dapat berisi beberapa warehouse dalam satu database ERP.</p></div><a class="btn primary" href="{{ route('admin.cycles.create') }}">+ Buat Cycle</a></div>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Cycle</th><th>Database</th><th>Warehouse</th><th>Assignment</th><th>Status</th><th>Mulai</th></tr></thead><tbody>@foreach($cycles as $cycle)<tr><td><a href="{{ route('admin.cycles.show',$cycle) }}"><strong>{{ $cycle->cycle_no }}</strong></a></td><td>{{ $cycle->source_database }}</td><td>{{ $cycle->warehouses_count }}</td><td>{{ $cycle->assignments_count }}</td><td><span class="status {{ strtolower($cycle->status) }}">{{ $cycle->status }}</span></td><td>{{ optional($cycle->started_at)->format('d/m/Y H:i') ?: '-' }}</td></tr>@endforeach</tbody></table></div>{{ $cycles->links() }}</section>
@endsection
