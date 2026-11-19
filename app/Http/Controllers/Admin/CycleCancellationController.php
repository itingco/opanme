<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockOpnameCycle;
use App\Services\MenuPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CycleCancellationController extends Controller
{
    private const CANCELLABLE = ['DRAFT', 'OPEN', 'CLOSED'];

    private function assertAdminAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['ADMIN', 'ADMIN_GUDANG'], true), 403);
        abort_unless(app(MenuPermissionService::class)->allowed($user->role, 'cycles'), 403);
    }

    /** Only expose action availability for IDs visible on the current list. */
    public function options(Request $request): JsonResponse
    {
        $this->assertAdminAccess($request);
        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer', 'min:1'],
        ]);
        $rows = StockOpnameCycle::query()
            ->whereIn('id', $validated['ids'])
            ->get(['id', 'status', 'created_by', 'cancelled_at', 'cancellation_reason']);
        $allowed = [];
        $cancelled = [];
        foreach ($rows as $cycle) {
            $allowed[(string) $cycle->id] = in_array($cycle->status, self::CANCELLABLE, true)
                && (int) $cycle->created_by === (int) $request->user()->id;
            if ($cycle->status === 'BATAL') {
                $cancelled[(string) $cycle->id] = [
                    'at' => $cycle->cancelled_at,
                    'reason' => $cycle->cancellation_reason,
                ];
            }
        }
        return response()->json(['options' => $allowed, 'cancelled' => $cancelled]);
    }

    public function cancel(Request $request, StockOpnameCycle $cycle): RedirectResponse
    {
        $this->assertAdminAccess($request);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        DB::transaction(function () use ($request, $cycle, $data): void {
            $locked = StockOpnameCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            abort_unless((int) $locked->created_by === (int) $request->user()->id, 403,
                'Hanya user pembuat cycle yang dapat membatalkan cycle ini.');
            if ($locked->status === StockOpnameCycle::STATUS_FINALIZED) {
                throw ValidationException::withMessages(['cycle' => 'Cycle FINALIZED tidak boleh dibatalkan.']);
            }
            if (!in_array($locked->status, self::CANCELLABLE, true)) {
                throw ValidationException::withMessages(['cycle' => 'Status cycle tidak dapat dibatalkan.']);
            }
            // Jangan hapus scan, snapshot, assignment, atau histori override.
            DB::table('scan_sessions')->where('cycle_id', $locked->id)->whereNull('ended_at')
                ->update(['ended_at' => now()]);
            DB::table('stock_opname_cycles')->where('id', $locked->id)->update([
                'status' => 'BATAL',
                'cancelled_at' => now(),
                'cancelled_by' => $request->user()->id,
                'cancellation_reason' => trim($data['reason']),
                'updated_at' => now(),
            ]);
        });
        return redirect()->route('admin.cycles.index')
            ->with('success', 'Cycle dibatalkan. Scan dan riwayat tetap disimpan untuk audit.');
    }
}
