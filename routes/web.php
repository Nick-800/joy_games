<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HardwareSimulatorController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShiftController;
use Illuminate\Support\Facades\Route;

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// All operational routes require authentication
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Session Operations
    Route::prefix('sessions')->name('sessions.')->group(function () {
        Route::post('/start', [SessionController::class, 'start'])->name('start');
        Route::post('/{session}/switch-tier', [SessionController::class, 'switchTier'])->name('switch-tier');
        Route::post('/{session}/extend', [SessionController::class, 'extendTime'])->name('extend');
        Route::post('/{session}/pause', [SessionController::class, 'pause'])->name('pause');
        Route::post('/{session}/resume', [SessionController::class, 'resume'])->name('resume');
        Route::post('/{session}/end', [SessionController::class, 'end'])->name('end');
        Route::post('/{session}/settle', [SessionController::class, 'settle'])->name('settle');
        Route::post('/{session}/transfer', [SessionController::class, 'transfer'])->name('transfer');
    });

    // POS Operations
    Route::prefix('pos')->name('pos.')->group(function () {
        Route::post('/items', [PosController::class, 'addItem'])->name('items.add');
        Route::delete('/items/{orderItem}', [PosController::class, 'removeItem'])->name('items.remove');
    });

    // Shift & Cashier Switcher
    Route::prefix('shift')->name('shift.')->group(function () {
        Route::post('/open', [ShiftController::class, 'open'])->name('open');
        Route::post('/close', [ShiftController::class, 'close'])->name('close');
        Route::post('/switch-cashier', [ShiftController::class, 'switchCashier'])->name('switch-cashier');
    });

    // Hardware Simulation Toolbar
    Route::prefix('simulator')->name('simulator.')->group(function () {
        Route::post('/stations/{station}/wake', [HardwareSimulatorController::class, 'simulateWake'])->name('wake');
        Route::post('/stations/{station}/sleep', [HardwareSimulatorController::class, 'simulateSleep'])->name('sleep');
        Route::post('/stations/{station}/rogue', [HardwareSimulatorController::class, 'triggerRogue'])->name('rogue');
        Route::post('/stations/{station}/fast-forward', [HardwareSimulatorController::class, 'fastForwardTime'])->name('fast-forward');
    });

    // Reconciliation
    Route::post('/reconciliation/sync', [ReconciliationController::class, 'sync'])->name('reconciliation.sync');

    // Invoice / Receipt
    Route::get('/sessions/{session}/invoice', [InvoiceController::class, 'show'])->name('sessions.invoice');

    // Reports (daily / weekly / monthly)
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/data', [ReportController::class, 'data'])->name('data');
        Route::get('/sessions', [ReportController::class, 'sessions'])->name('sessions');
        Route::get('/export', [ReportController::class, 'export'])->name('export');
    });

    // Settings (Station Management, TV Control Feature Flag, Pricing)
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::post('/stations', [SettingsController::class, 'storeStation'])->name('stations.store');
        Route::put('/stations/{station}', [SettingsController::class, 'updateStation'])->name('stations.update');
        Route::delete('/stations/{station}', [SettingsController::class, 'destroyStation'])->name('stations.destroy');
        Route::post('/feature-flags', [SettingsController::class, 'updateFeatureFlags'])->name('feature-flags.update');
        Route::put('/pricing-tiers/{tier}', [SettingsController::class, 'updatePricingTier'])->name('pricing-tiers.update');
    });
});
