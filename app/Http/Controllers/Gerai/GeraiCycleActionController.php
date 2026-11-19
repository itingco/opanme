<?php
namespace App\Http\Controllers\Gerai;

use App\Http\Controllers\Controller;
use App\Models\SampleCycle;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GeraiCycleActionController extends Controller
{
    public function cancel(Request $request, SampleCycle $sampleCycle): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isAdminGerai(), 403);
        DB::transaction(function () use ($user, $sampleCycle): void {
            $cycle = SampleCycle::query()->lockForUpdate()->findOrFail($sampleCycle->id);
            abort_unless($cycle->cycle_type === SampleCycle::TYPE_GERAI, 404);
            abort_unless((int)$cycle->created_by === (int)$user->id, 403, 'Hanya pembuat cycle boleh membatalkan.');
            if (!in_array($cycle->status, ['OPEN','CLOSED'], true)) {
                throw ValidationException::withMessages(['cycle' => 'Hanya cycle OPEN atau CLOSED yang dapat dibatalkan.']);
            }
            $cycle->update([
                'status' => 'BATAL',
                'closed_at' => $cycle->closed_at ?? now(),
                'notes' => trim((string)$cycle->notes."\n[BATAL] ".now()->format('Y-m-d H:i:s')." oleh {$user->username}"),
            ]);
        });
        return back()->with('success', 'Cycle dibatalkan tanpa menghapus riwayat scan.');
    }

    public function myCycles(Request $request): \Illuminate\View\View
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isAdminGerai(), 403);
        $cycles = SampleCycle::query()->where('cycle_type', SampleCycle::TYPE_GERAI)
            ->where('created_by', $request->user()->id)
            ->whereIn('status', ['OPEN','CLOSED'])
            ->latest('id')->paginate(25);
        return view('gerai.cycle-audit-my-cycles', compact('cycles'));
    }

    public function createAuditor(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name' => ['required','string','max:150'],
            'username' => ['required','alpha_dash','max:100',Rule::unique('users','username')],
            'password' => ['required','string','min:8'],
        ]);
        User::create(array_merge($data, ['role' => 'CYCLE_AUDITOR', 'is_active' => true]));
        return back()->with('success', 'User auditor read-only berhasil dibuat.');
    }
}
