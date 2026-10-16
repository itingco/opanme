<?php

namespace App\Http\Controllers\Checker;

use App\Models\CheckerAssignment;
use App\Models\ScanSession;
use App\Models\StockOpnameCycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegacyWarehouseCheckerController extends CheckerController
{
    public function home(Request $request): View
    {
        $assignments = CheckerAssignment::query()
            ->with(['cycle', 'warehouse'])
            ->where('checker_id', $request->user()->id)
            ->whereHas('cycle', fn ($query) => $query->where('status', StockOpnameCycle::STATUS_OPEN))
            ->get()
            ->sortBy(fn ($assignment) => $assignment->cycle->cycle_no.'-'.$assignment->warehouse->warehouse_code);

        return view('checker.warehouse-legacy-home', compact('assignments'));
    }

    public function startSession(Request $request, StockOpnameCycle $cycle, int $warehouseId): RedirectResponse
    {
        $data = $request->validate(['location' => ['required', 'string', 'max:255']]);
        abort_unless($cycle->status === StockOpnameCycle::STATUS_OPEN, 422);
        $warehouse = $cycle->warehouses()->findOrFail($warehouseId);
        abort_unless(
            CheckerAssignment::query()->where('cycle_id', $cycle->id)
                ->where('warehouse_id', $warehouse->id)
                ->where('checker_id', $request->user()->id)->exists(),
            403
        );
        ScanSession::where('checker_id', $request->user()->id)->whereNull('ended_at')->update(['ended_at' => now()]);
        $session = ScanSession::create([
            'cycle_id' => $cycle->id,
            'warehouse_id' => $warehouse->id,
            'checker_id' => $request->user()->id,
            'location' => trim($data['location']),
            'started_at' => now(),
        ]);
        return redirect()->route('warehouse.legacy.scan', $session);
    }

    public function scanPage(Request $request, ScanSession $session): View
    {
        $original = parent::scanPage($request, $session);
        return view('checker.warehouse-legacy-scan', $original->getData());
    }
}
