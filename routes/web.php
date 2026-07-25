<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BankReconciliationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\DonationPortalController;
use App\Http\Controllers\DonorController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryCashController;
use App\Http\Controllers\InventoryStockAdjustmentController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'role:admin,secretary,treasurer'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/donors', [DonorController::class, 'index'])->name('donors.index');
    Route::post('/donors', [DonorController::class, 'store'])->name('donors.store');
    Route::get('/donors/{donor}', [DonorController::class, 'show'])->name('donors.show');
    Route::patch('/donors/{donor}', [DonorController::class, 'update'])->name('donors.update');
    Route::get('/donations', [DonationPortalController::class, 'index'])->name('donations.index');
    Route::post('/donations', [DonationPortalController::class, 'store'])->name('donations.store');
    Route::get('/donations/{contribution}', [DonationPortalController::class, 'show'])->name('donations.show');
    Route::patch('/donations/{contribution}', [DonationPortalController::class, 'update'])->name('donations.update');
    Route::patch('/donations/{contribution}/verify', [DonationPortalController::class, 'verify'])->name('donations.verify');

    Route::get('/role-workflows', [InventoryCashController::class, 'index'])->name('workflows.index');
    Route::post('/role-workflows/inventory', [InventoryCashController::class, 'storeInventory'])->name('workflows.inventory.store');
    Route::post('/role-workflows/cash', [InventoryCashController::class, 'storeCash'])->name('cash.store');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::get('/inventory/{inventoryItem}', [InventoryController::class, 'show'])->name('inventory.show');
    Route::patch('/inventory/{inventoryItem}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::post('/inventory/{inventoryItem}/adjustments', [InventoryStockAdjustmentController::class, 'store'])
        ->name('inventory.adjustments.store');

    Route::get('/bank-reconciliation', [BankReconciliationController::class, 'index'])->name('bank-reconciliation.index');
    Route::post('/bank-reconciliation/reconcile', [BankReconciliationController::class, 'reconcile'])->name('bank-reconciliation.reconcile');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/financial-ledgers/csv', [ReportController::class, 'financialLedgersCsv'])->name('reports.financial-ledgers.csv');
});
