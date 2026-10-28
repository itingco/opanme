@extends('layouts.app')
@section('title','Hak Akses Menu per Role')
@section('content')
<div class="page-heading"><div><h1>Hak Akses Menu per Role</h1><p>Centang modul yang boleh diakses oleh masing-masing role, lalu simpan. Pengaturan berlaku untuk sidebar dan akses URL.</p></div></div>
<section class="panel" style="padding:20px">
    <form method="POST" action="{{ route('admin.menu-permissions.update') }}">
        @csrf @method('PUT')
        <div class="table-wrap" style="overflow:auto;max-width:100%">
            <table style="border-collapse:collapse;width:100%;min-width:1000px">
                <thead><tr><th style="position:sticky;left:0;background:#fff;padding:14px;text-align:left;z-index:2">Menu / Modul</th>
                    @foreach($roles as $role)<th style="padding:12px;text-align:center;white-space:nowrap">{{ $role }}</th>@endforeach
                </tr></thead>
                <tbody>
                @foreach($menus as $key => $menu)
                    <tr style="border-top:1px solid #ddd">
                        <td style="position:sticky;left:0;background:#fff;padding:14px;min-width:220px"><strong>{{ $menu['label'] }}</strong></td>
                        @foreach($roles as $role)
                            <td style="padding:12px;text-align:center">
                                <input aria-label="Izinkan {{ $role }}: {{ $menu['label'] }}" type="checkbox" name="access[{{ $role }}][{{ $key }}]" value="1" @checked($permissions->allowed($role,$key)) style="height:20px;width:20px;cursor:pointer">
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:12px;align-items:center;margin-top:20px;flex-wrap:wrap">
            <small>Halaman pengaturan ini selalu dapat diakses oleh role ADMIN.</small>
            <button class="btn primary" type="submit" onclick="return confirm('Simpan seluruh pengaturan hak akses menu?')">Simpan Hak Akses</button>
        </div>
    </form>
</section>
@endsection
