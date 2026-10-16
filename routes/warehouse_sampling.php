<?php

use App\Http\Controllers\Admin\WarehouseSamplingEnhancedController;
use App\Http\Controllers\Admin\WarehouseSamplingHistoryEnhancedController;
use App\Http\Controllers\Checker\LegacyWarehouseCheckerController;
use App\Http\Controllers\Checker\ScanController;
use App\Http\Controllers\Warehouse\CheckerSamplingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:ADMIN,ADMIN_GUDANG'])
    ->prefix('admin/warehouse-sampling')
    ->name('warehouse.history.')
    ->group(function () {
        Route::get('/history', [WarehouseSamplingHistoryEnhancedController::class, 'index'])->name('index');
        Route::get('/history/export-pdf', [WarehouseSamplingHistoryEnhancedController::class, 'exportPdf'])->name('pdf');
        Route::get('/history/export-excel', [WarehouseSamplingHistoryEnhancedController::class, 'exportExcel'])->name('excel');
    });

Route::middleware(['auth', 'role:ADMIN,ADMIN_GUDANG'])
    ->prefix('admin/warehouse-sampling')
    ->name('warehouse.admin.')
    ->group(function () {
        Route::get('/', [WarehouseSamplingEnhancedController::class, 'index'])->name('index');
        Route::post('/', [WarehouseSamplingEnhancedController::class, 'store'])->name('store');
        Route::get('/warehouses', [WarehouseSamplingEnhancedController::class, 'warehouses'])->name('warehouses');
        Route::get('/periods/{sampleCycle}', [WarehouseSamplingEnhancedController::class, 'show'])->name('show');
        Route::put('/periods/{sampleCycle}', [WarehouseSamplingEnhancedController::class, 'update'])->name('update');
        Route::get('/periods/{sampleCycle}/available-items', [WarehouseSamplingEnhancedController::class, 'availableItems'])->name('items.available');
        Route::post('/periods/{sampleCycle}/items', [WarehouseSamplingEnhancedController::class, 'addItems'])->name('items.store');
        Route::delete('/periods/{sampleCycle}/items/{sampleCycleItem}', [WarehouseSamplingEnhancedController::class, 'removeItem'])->name('items.destroy');
        Route::put('/periods/{sampleCycle}/items/{sampleCycleItem}/validate', [WarehouseSamplingEnhancedController::class, 'validateItem'])->name('items.validate');
        Route::post('/periods/{sampleCycle}/validate-bulk', [WarehouseSamplingEnhancedController::class, 'validateBulk'])->name('items.validate-bulk');
        Route::post('/periods/{sampleCycle}/release', [WarehouseSamplingEnhancedController::class, 'release'])->name('release');
        Route::post('/periods/{sampleCycle}/close', [WarehouseSamplingEnhancedController::class, 'close'])->name('close');
    });

Route::middleware(['auth', 'role:CHECKER_GUDANG'])
    ->prefix('warehouse-checker')
    ->name('warehouse.checker.')
    ->group(function () {
        Route::get('/', [CheckerSamplingController::class, 'index'])->name('index');
        Route::get('/periods/{sampleCycle}', [CheckerSamplingController::class, 'show'])->name('show');
        Route::post('/periods/{sampleCycle}/finalize', [CheckerSamplingController::class, 'finalize'])->name('finalize');
        Route::put('/periods/{sampleCycle}/items/{sampleCycleItem}', [CheckerSamplingController::class, 'check'])->name('check');
    });

// Opname Legacy bagi checker gudang, tanpa mengubah hak akses role CHECKER lama.
Route::middleware(['auth', 'role:CHECKER_GUDANG'])
    ->prefix('warehouse-legacy-checker')
    ->name('warehouse.legacy.')
    ->group(function () {
        Route::get('/', [LegacyWarehouseCheckerController::class, 'home'])->name('home');
        Route::post('/cycles/{cycle}/warehouses/{warehouseId}/session', [LegacyWarehouseCheckerController::class, 'startSession'])->name('session.start');
        Route::get('/sessions/{session}/scan', [LegacyWarehouseCheckerController::class, 'scanPage'])->name('scan');
        Route::post('/sessions/{session}/scan', [ScanController::class, 'store'])->name('scan.store');
        Route::post('/sessions/{session}/scan/non-system', [ScanController::class, 'storeNonSystem'])->name('scan.non-system');
    });
