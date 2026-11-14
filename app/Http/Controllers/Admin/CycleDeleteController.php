<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockOpnameCycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CycleDeleteController extends Controller
{
    public function __invoke(Request $request, StockOpnameCycle $cycle): RedirectResponse
    {
        $cycleNo = $cycle->cycle_no;

        DB::transaction(function () use ($request, $cycle): void {
            $locked = StockOpnameCycle::query()
                ->lockForUpdate()
                ->findOrFail($cycle->id);

            if ($locked->status !== StockOpnameCycle::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'cycle' => 'Cycle hanya dapat dihapus selama masih berstatus DRAFT.',
                ]);
            }

            if ((int) $locked->created_by !== (int) $request->user()->id) {
                abort(403, 'Cycle hanya dapat dihapus oleh admin yang membuat cycle tersebut.');
            }

            $locked->delete();
        });

        return redirect()
            ->route('admin.cycles.index')
            ->with('success', "Cycle {$cycleNo} berhasil dihapus.");
    }
}
