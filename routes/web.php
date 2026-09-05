<?php

use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\BarcodeController;
use App\Http\Controllers\Admin\CycleController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ErpLookupController;
use App\Http\Controllers\Admin\RatioController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Checker\CheckerController;
use App\Http\Controllers\Checker\ScanController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('/', fn () => auth()->check() ? redirect(auth()->user()->isAdmin() ? route('admin.dashboard') : route('checker.home')) : redirect()->route('login'));

Route::middleware(['auth','role:ADMIN'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/users', [UserController::class,'index'])->name('users.index');
    Route::post('/users', [UserController::class,'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class,'update'])->name('users.update');

    Route::get('/barcodes', [BarcodeController::class,'index'])->name('barcodes.index');
    Route::get('/barcodes/template', [BarcodeController::class,'template'])->name('barcodes.template');
    Route::get('/barcodes/export', [BarcodeController::class,'export'])->name('barcodes.export');
    Route::post('/barcodes/import', [BarcodeController::class,'import'])->name('barcodes.import');
    Route::post('/barcodes', [BarcodeController::class,'store'])->name('barcodes.store');
    Route::delete('/barcodes/{barcode}', [BarcodeController::class,'destroy'])->name('barcodes.destroy');

    Route::get('/ratios', [RatioController::class,'index'])->name('ratios.index');
    Route::get('/ratios/template', [RatioController::class,'template'])->name('ratios.template');
    Route::get('/ratios/export', [RatioController::class,'export'])->name('ratios.export');
    Route::post('/ratios/import', [RatioController::class,'import'])->name('ratios.import');
    Route::post('/ratios', [RatioController::class,'store'])->name('ratios.store');
    Route::delete('/ratios/{ratio}', [RatioController::class,'destroy'])->name('ratios.destroy');
    Route::get('/erp/warehouses', [ErpLookupController::class,'warehouses'])->name('erp.warehouses');
    Route::get('/erp/barcode', [ErpLookupController::class,'barcode'])->name('erp.barcode');
    Route::get('/cycles', [CycleController::class,'index'])->name('cycles.index');
    Route::get('/cycles/create', [CycleController::class,'create'])->name('cycles.create');
    Route::post('/cycles', [CycleController::class,'store'])->name('cycles.store');
    Route::get('/cycles/{cycle}', [CycleController::class,'show'])->name('cycles.show');
    Route::get('/cycles/{cycle}/assignments', [AssignmentController::class,'edit'])->name('cycles.assignments.edit');
    Route::put('/cycles/{cycle}/assignments', [AssignmentController::class,'update'])->name('cycles.assignments.update');
    Route::post('/cycles/{cycle}/start', [CycleController::class,'start'])->name('cycles.start');
    Route::post('/cycles/{cycle}/close', [CycleController::class,'close'])->name('cycles.close');
    Route::post('/cycles/{cycle}/retry-closing', [CycleController::class,'retryClosing'])->name('cycles.retry-closing');
    Route::post('/cycles/{cycle}/finalize', [CycleController::class,'finalize'])->name('cycles.finalize');
    Route::get('/cycles/{cycle}/final-report', [CycleController::class,'finalReport'])->name('cycles.final-report');
    Route::get('/cycles/{cycle}/summary', [CycleController::class,'summary'])->name('cycles.summary');
    Route::get('/cycles/{cycle}/summary-export', [CycleController::class,'exportSummary'])->name('cycles.summary.export');
    Route::post('/cycles/{cycle}/summary/non-system/override', [CycleController::class,'createDiscoveredOverride'])->name('cycles.summary.non-system.store');
    Route::put('/cycles/{cycle}/summary/non-system/{warehouseId}/{discoveredItemId}/override', [CycleController::class,'saveDiscoveredOverride'])->name('cycles.summary.non-system.override');
    Route::delete('/cycles/{cycle}/summary/non-system/{warehouseId}/{discoveredItemId}/override', [CycleController::class,'deleteDiscoveredOverride'])->name('cycles.summary.non-system.override.destroy');
    Route::get('/cycles/{cycle}/summary/non-system/{warehouseId}/{discoveredItemId}', [CycleController::class,'scanDetailNonSystem'])->name('cycles.scan-detail.non-system');
    Route::put('/cycles/{cycle}/summary/{warehouseId}/{itemId}/override', [CycleController::class,'saveOverride'])->name('cycles.summary.override');
    Route::delete('/cycles/{cycle}/summary/{warehouseId}/{itemId}/override', [CycleController::class,'deleteOverride'])->name('cycles.summary.override.destroy');
    Route::get('/cycles/{cycle}/summary/{warehouseId}/{itemId}', [CycleController::class,'scanDetail'])->name('cycles.scan-detail');
});

Route::middleware(['auth','role:CHECKER'])->prefix('checker')->name('checker.')->group(function () {
    Route::get('/', [CheckerController::class,'home'])->name('home');
    Route::post('/cycles/{cycle}/warehouses/{warehouseId}/session', [CheckerController::class,'startSession'])->name('session.start');
    Route::get('/sessions/{session}/scan', [CheckerController::class,'scanPage'])->name('scan');
    Route::post('/sessions/{session}/scan', [ScanController::class,'store'])->name('scan.store');
    Route::post('/sessions/{session}/scan/non-system', [ScanController::class,'storeNonSystem'])->name('scan.non-system');
});
