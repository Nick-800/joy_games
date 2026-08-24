<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HardwareSimulatorController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\ShiftController;
use Illuminate\Support\Facades\Route;

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
