<?php

use App\Http\Controllers\Label\ItemLookupController;
use App\Http\Controllers\Label\LabelController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('label')->name('label.')->group(function () {
    Route::get('/', [LabelController::class, 'index'])->name('index');
    Route::get('/items', ItemLookupController::class)->name('items.index');
    Route::post('/preview', [LabelController::class, 'preview'])->name('preview');
});
