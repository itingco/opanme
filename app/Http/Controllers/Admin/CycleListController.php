<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockOpnameCycle;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CycleListController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $warehouse = trim((string) $request->query('warehouse', ''));
        $checker = trim((string) $request->query('checker', ''));
        $database = (string) $request->query('source_database', '');
        $status = (string) $request->query('status', '');
        $perPage = (int) $request->query('per_page', 20);

        if (! in_array($database, ['AS_INGCO', 'AS_SMI'], true)) $database = '';
        if (! in_array($status, ['DRAFT', 'OPEN', 'CLOSED', 'FINALIZED'], true)) $status = '';
        if (! in_array($perPage, [10, 20, 50, 100], true)) $perPage = 20;

        $cycles = StockOpnameCycle::query()
            ->with(['warehouses', 'assignments.checker'])
            ->withCount(['warehouses', 'assignments'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('cycle_no', 'like', '%'.$q.'%')
                        ->orWhere('source_database', 'like', '%'.$q.'%');
                });
            })
            ->when($warehouse !== '', function ($query) use ($warehouse) {
                $query->whereHas('warehouses', function ($inner) use ($warehouse) {
                    $inner->where(function ($filter) use ($warehouse) {
                        $filter->where('warehouse_code', 'like', '%'.$warehouse.'%')
                            ->orWhere('warehouse_name', 'like', '%'.$warehouse.'%');
                    });
                });
            })
            ->when($checker !== '', function ($query) use ($checker) {
                $query->whereHas('assignments.checker', function ($inner) use ($checker) {
                    $inner->where(function ($filter) use ($checker) {
                        $filter->where('name', 'like', '%'.$checker.'%')
                            ->orWhere('username', 'like', '%'.$checker.'%');
                    });
                });
            })
            ->when($database !== '', fn ($query) => $query->where('source_database', $database))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.cycles.index', compact('cycles', 'q', 'warehouse', 'checker', 'database', 'status', 'perPage'));
    }
}
