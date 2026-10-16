<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CheckerAssignment;
use App\Models\StockOpnameCycle;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    private function eligibleCheckerIds(): array
    {
        return User::query()
            ->whereIn('role', [User::ROLE_CHECKER, User::ROLE_CHECKER_GUDANG])
            ->where('is_active', true)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function edit(StockOpnameCycle $cycle): View
    {
        abort_unless($cycle->status === StockOpnameCycle::STATUS_DRAFT, 422);
        $cycle->load(['warehouses', 'assignments']);
        $checkers = User::query()
            ->whereIn('role', [User::ROLE_CHECKER, User::ROLE_CHECKER_GUDANG])
            ->where('is_active', true)
            ->orderBy('name')->get();
        $selected = $cycle->assignments->groupBy('warehouse_id')->map(fn ($rows) => $rows->pluck('checker_id')->all());
        return view('admin.cycles.assignments', compact('cycle', 'checkers', 'selected'));
    }

    public function update(Request $request, StockOpnameCycle $cycle): RedirectResponse
    {
        abort_unless($cycle->status === StockOpnameCycle::STATUS_DRAFT, 422);
        $data = $request->validate([
            'assignments' => ['nullable', 'array'],
            'assignments.*' => ['array'],
            'assignments.*.*' => ['integer', 'exists:users,id'],
        ]);
        $validWarehouses = array_map('intval', $cycle->warehouses()->pluck('id')->all());
        $validCheckers = $this->eligibleCheckerIds();
        DB::transaction(function () use ($cycle, $data, $validWarehouses, $validCheckers, $request): void {
            CheckerAssignment::where('cycle_id', $cycle->id)->delete();
            foreach (($data['assignments'] ?? []) as $warehouseId => $checkerIds) {
                if (! in_array((int) $warehouseId, $validWarehouses, true)) continue;
                foreach (array_unique($checkerIds) as $checkerId) {
                    if (! in_array((int) $checkerId, $validCheckers, true)) continue;
                    CheckerAssignment::create([
                        'cycle_id' => $cycle->id,
                        'warehouse_id' => (int) $warehouseId,
                        'checker_id' => (int) $checkerId,
                        'assigned_by' => $request->user()->id,
                        'created_at' => now(),
                    ]);
                }
            }
        });
        return redirect()->route('admin.cycles.show', $cycle)->with('success', 'Assignment checker tersimpan.');
    }
}
