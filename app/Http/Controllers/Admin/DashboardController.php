<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscoveredScanTransaction;
use App\Models\ScanTransaction;
use App\Models\StockOpnameCycle;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'openCycles' => StockOpnameCycle::where('status', StockOpnameCycle::STATUS_OPEN)->latest()->get(),
            'recentCycles' => StockOpnameCycle::latest()->limit(8)->get(),
            'checkerCount' => User::where('role', User::ROLE_CHECKER)->where('is_active', true)->count(),
            'todayScans' => ScanTransaction::whereDate('scanned_at', today())->count()
                + DiscoveredScanTransaction::whereDate('scanned_at', today())->count(),
        ]);
    }
}
