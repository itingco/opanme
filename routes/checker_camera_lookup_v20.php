<?php

use App\Http\Controllers\Checker\CameraBarcodeLookupController;
use App\Http\Controllers\Checker\CameraManualController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:CHECKER'])->prefix('checker')->name('checker.')->group(function () {
    Route::get('/sessions/{session}/camera-barcode-lookup', CameraBarcodeLookupController::class)
        ->name('camera-barcode.lookup');
    // Unique path: older multipart photo forms also use /manual-photo.
    Route::post('/sessions/{session}/camera-manual-photo-v21', [CameraManualController::class, 'store'])
        ->name('camera-manual-v21.store');
});
Route::middleware(['auth', 'role:CHECKER_GUDANG'])->prefix('warehouse-legacy-checker')->name('warehouse.legacy.')->group(function () {
    Route::get('/sessions/{session}/camera-barcode-lookup', CameraBarcodeLookupController::class)
        ->name('camera-barcode.lookup');
    Route::post('/sessions/{session}/camera-manual-photo-v21', [CameraManualController::class, 'store'])
        ->name('camera-manual-v21.store');
});
