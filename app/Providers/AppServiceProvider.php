<?php
namespace App\Providers;

use App\Http\Middleware\GeraiCycleOverlay;
use App\Services\GeraiCycleCheckingService;
use App\Services\SampleCheckingService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SampleCheckingService::class, GeraiCycleCheckingService::class);
    }

    public function boot(): void
    {
        View::composer([
            'warehouse_sampling.admin.index',
            'warehouse_sampling.history.compact',
            'warehouse_sampling.history.index',
        ], static function (): void {
            Paginator::defaultView('pagination.warehouse-sampling');
        });

        // Tambahan route tetap bekerja tanpa mengubah routes/web.php.
        // Didaftarkan setelah semua route bawaan selesai dimuat.
        $this->app->booted(function (): void {
            Route::middleware(['web', 'auth', 'role:ADMIN,ADMIN_GERAI,CYCLE_AUDITOR'])
                ->prefix('cycle-audit')->name('cycle-audit.')->group(function (): void {
                    Route::get('/', [\App\Http\Controllers\Gerai\CycleAuditController::class, 'index'])->name('index');
                    Route::get('/gerai/{sampleCycle}', [\App\Http\Controllers\Gerai\CycleAuditController::class, 'gerai'])->name('gerai');
                    Route::get('/final/{cycle}', [\App\Http\Controllers\Gerai\CycleAuditController::class, 'final'])->name('final');
                    Route::post('/auditors', [\App\Http\Controllers\Gerai\GeraiCycleActionController::class, 'createAuditor'])->name('auditors.store');
                    Route::get('/my-cycles', [\App\Http\Controllers\Gerai\GeraiCycleActionController::class, 'myCycles'])->name('my-cycles');
                });
            // Pembatalan cycle utama memakai URL terpisah agar tidak berbenturan
            // dengan route /admin/cycles/{cycle} yang sudah ada.
            Route::middleware(['web', 'auth', 'role:ADMIN,ADMIN_GUDANG'])
                ->prefix('admin/cycle-cancel')->name('admin.cycles.')->group(function (): void {
                    Route::get('/options', [\App\Http\Controllers\Admin\CycleCancellationController::class, 'options'])->name('cancel-options');
                    Route::post('/{cycle}', [\App\Http\Controllers\Admin\CycleCancellationController::class, 'cancel'])->name('cancel');
                });
            Route::middleware(['web', 'auth', 'role:ADMIN,ADMIN_GERAI'])
                ->patch('/gerai-admin/cycles/{sampleCycle}/cancel', [\App\Http\Controllers\Gerai\GeraiCycleActionController::class, 'cancel'])
                ->name('gerai.admin.cancel');
        });

        // Script overlay ditempel ke HTML halaman scanner; kode scanner lama tetap digunakan.
        $this->app['router']->pushMiddlewareToGroup('web', GeraiCycleOverlay::class);
    }
}
