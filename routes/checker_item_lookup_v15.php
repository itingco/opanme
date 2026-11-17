<?php

use App\Http\Controllers\Checker\CameraItemLookupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:CHECKER'])->prefix('checker')->name('checker.')->group(function () {
    Route::get('/sessions/{session}/manual-item-lookup', CameraItemLookupController::class)->name('manual-item.lookup');
});
Route::middleware(['auth', 'role:CHECKER_GUDANG'])->prefix('warehouse-legacy-checker')->name('warehouse.legacy.')->group(function () {
    Route::get('/sessions/{session}/manual-item-lookup', CameraItemLookupController::class)->name('manual-item.lookup');
});
