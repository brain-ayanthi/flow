<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\JobSheetController;
use App\Http\Controllers\Web\SettingsPageController;
use App\Http\Controllers\Web\TaxPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:8,1')->name('login.store');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::middleware('tenant.context')->get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/orders/{orderId}/job-sheet', [JobSheetController::class, 'show'])
        ->whereNumber('orderId')->middleware('tenant.context')->name('orders.job-sheet');
    Route::get('/taxes', TaxPageController::class)
        ->middleware(['tenant.context', 'tenant.permission:taxes.view'])
        ->name('taxes.index');
    Route::get('/settings', SettingsPageController::class)
        ->middleware(['tenant.context', 'tenant.permission:settings.manage'])
        ->name('settings.index');
});
