<?php

use App\Http\Controllers\Checker\CameraManualController;
use App\Http\Controllers\Checker\CameraAiReadController;
use Illuminate\Support\Facades\Route;

if (!Route::has('checker.camera-manual.store') || !Route::has('checker.camera-ai.read')) {
    Route::middleware(['auth', 'role:CHECKER'])->prefix('checker')->name('checker.')->group(function () {
        if (!Route::has('checker.camera-manual.store')) {
            Route::post('/sessions/{session}/manual-photo', [CameraManualController::class, 'store'])->name('camera-manual.store');
        }
        if (!Route::has('checker.camera-ai.read')) {
            Route::post('/sessions/{session}/camera-ai', CameraAiReadController::class)->name('camera-ai.read');
        }
    });
}
if (!Route::has('warehouse.legacy.camera-manual.store') || !Route::has('warehouse.legacy.camera-ai.read')) {
    Route::middleware(['auth', 'role:CHECKER_GUDANG'])->prefix('warehouse-legacy-checker')->name('warehouse.legacy.')->group(function () {
        if (!Route::has('warehouse.legacy.camera-manual.store')) {
            Route::post('/sessions/{session}/manual-photo', [CameraManualController::class, 'store'])->name('camera-manual.store');
        }
        if (!Route::has('warehouse.legacy.camera-ai.read')) {
            Route::post('/sessions/{session}/camera-ai', CameraAiReadController::class)->name('camera-ai.read');
        }
    });
}
