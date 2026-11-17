<?php

use App\Http\Controllers\Checker\CameraBarcodeLookupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:CHECKER'])->prefix('checker')->name('checker.')->group(function () {
    Route::get('/sessions/{session}/camera-barcode-lookup', CameraBarcodeLookupController::class)
        ->name('camera-barcode.lookup');
});
Route::middleware(['auth', 'role:CHECKER_GUDANG'])->prefix('warehouse-legacy-checker')->name('warehouse.legacy.')->group(function () {
    Route::get('/sessions/{session}/camera-barcode-lookup', CameraBarcodeLookupController::class)
        ->name('camera-barcode.lookup');
});
