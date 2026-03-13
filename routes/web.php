<?php

use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\BitrixContextController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\SmartProcessTemplateController;
use Illuminate\Support\Facades\Route;

Route::match(['GET', 'POST'], '/bitrix/local/install', [BitrixContextController::class, 'install'])
    ->name('bitrix.install');

Route::match(['GET', 'POST'], '/bitrix/local/app', [BitrixContextController::class, 'entry'])
    ->middleware('bitrix.context')
    ->name('bitrix.entry');

Route::get('/public/import-errors/{importJob}', [ImportController::class, 'downloadErrorsPublic'])
    ->middleware('signed')
    ->name('imports.errors.public');

Route::prefix('app')->middleware('bitrix.context')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard.index');

    Route::get('/templates/{entityTypeId}', [SmartProcessTemplateController::class, 'download'])
        ->whereNumber('entityTypeId')
        ->name('templates.download');

    Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
    Route::get('/imports/{importJob}', [ImportController::class, 'show'])->name('imports.show');
    Route::get('/imports/{importJob}/status', [ImportController::class, 'status'])->name('imports.status');
    Route::get('/imports/{importJob}/errors.xlsx', [ImportController::class, 'downloadErrors'])->name('imports.errors');

    Route::get('/admin/permissions', [PermissionController::class, 'index'])->name('admin.permissions.index');
    Route::post('/admin/permissions/{entityTypeId}', [PermissionController::class, 'update'])
        ->whereNumber('entityTypeId')
        ->name('admin.permissions.update');
});

Route::get('/', static fn () => redirect()->route('dashboard.index'));
