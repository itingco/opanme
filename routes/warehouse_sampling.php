<?php

use App\Http\Controllers\Admin\WarehouseSamplingController;
use App\Http\Controllers\Warehouse\CheckerSamplingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:ADMIN,ADMIN_GUDANG'])
    ->prefix('admin/warehouse-sampling')
    ->name('warehouse.admin.')
    ->group(function () {
        Route::get('/', [WarehouseSamplingController::class, 'index'])->name('index');
        Route::post('/', [WarehouseSamplingController::class, 'store'])->name('store');
        Route::get('/warehouses', [WarehouseSamplingController::class, 'warehouses'])->name('warehouses');
        Route::get('/periods/{sampleCycle}', [WarehouseSamplingController::class, 'show'])->name('show');
        Route::put('/periods/{sampleCycle}', [WarehouseSamplingController::class, 'update'])->name('update');
        Route::get('/periods/{sampleCycle}/item-search', [WarehouseSamplingController::class, 'itemSearch'])->name('items.search');
        Route::post('/periods/{sampleCycle}/items', [WarehouseSamplingController::class, 'addItem'])->name('items.store');
        Route::delete('/periods/{sampleCycle}/items/{sampleCycleItem}', [WarehouseSamplingController::class, 'removeItem'])->name('items.destroy');
        Route::post('/periods/{sampleCycle}/release', [WarehouseSamplingController::class, 'release'])->name('release');
        Route::post('/periods/{sampleCycle}/close', [WarehouseSamplingController::class, 'close'])->name('close');
    });

Route::middleware(['auth', 'role:CHECKER_GUDANG'])
    ->prefix('warehouse-checker')
    ->name('warehouse.checker.')
    ->group(function () {
        Route::get('/', [CheckerSamplingController::class, 'index'])->name('index');
        Route::get('/periods/{sampleCycle}', [CheckerSamplingController::class, 'show'])->name('show');
        Route::put('/periods/{sampleCycle}/items/{sampleCycleItem}', [CheckerSamplingController::class, 'check'])->name('check');
    });
