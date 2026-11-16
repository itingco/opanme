<?php
// Registered explicitly from routes/web.php, independent of earlier installers.
use App\Http\Controllers\Checker\CameraManualController;
use App\Http\Controllers\Checker\CameraAiReadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:CHECKER'])
    ->prefix('checker')
    ->name('checker.')
    ->group(function () {
        Route::post('/sessions/{session}/manual-photo', [CameraManualController::class, 'store'])
            ->name('camera-manual.store');
        Route::post('/sessions/{session}/camera-ai', CameraAiReadController::class)
            ->name('camera-ai.read');
    });

Route::middleware(['auth', 'role:CHECKER_GUDANG'])
    ->prefix('warehouse-legacy-checker')
    ->name('warehouse.legacy.')
    ->group(function () {
        Route::post('/sessions/{session}/manual-photo', [CameraManualController::class, 'store'])
            ->name('camera-manual.store');
        Route::post('/sessions/{session}/camera-ai', CameraAiReadController::class)
            ->name('camera-ai.read');
    });
