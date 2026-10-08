<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\BankReconciliationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisbursementController;
use App\Http\Controllers\DonationPortalController;
use App\Http\Controllers\DonorController;
use App\Http\Controllers\InventoryCategoryController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryStockAdjustmentController;
use App\Http\Controllers\InventorySubcategoryController;
use App\Http\Controllers\InventoryUnitController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SystemSecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/password/change', [ChangePasswordController::class, 'create'])->name('password.change');
    Route::post('/password/change', [ChangePasswordController::class, 'store'])->name('password.change.store');
});

Route::middleware(['auth', 'password.change', 'role:admin,secretary,treasurer'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/donors', [DonorController::class, 'index'])->name('donors.index');
    Route::post('/donors', [DonorController::class, 'store'])->name('donors.store');
    Route::get('/donors/{donor}', [DonorController::class, 'show'])->name('donors.show');
    Route::patch('/donors/{donor}', [DonorController::class, 'update'])->name('donors.update');
    Route::delete('/donors/{donor}', [DonorController::class, 'destroy'])->name('donors.destroy');
    Route::get('/donations', [DonationPortalController::class, 'index'])->name('donations.index');
    Route::post('/donations', [DonationPortalController::class, 'store'])->name('donations.store');
    Route::get('/donations/{contribution}', [DonationPortalController::class, 'show'])->name('donations.show');
    Route::patch('/donations/{contribution}', [DonationPortalController::class, 'update'])->name('donations.update');
    Route::patch('/donations/{contribution}/verify', [DonationPortalController::class, 'verify'])->name('donations.verify');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::get('/inventory/export/pdf', [InventoryController::class, 'exportPdf'])->name('inventory.export.pdf');

    Route::get('/inventory/categories', [InventoryCategoryController::class, 'index'])->name('inventory.categories.index');
    Route::post('/inventory/categories', [InventoryCategoryController::class, 'store'])->name('inventory.categories.store');
    Route::patch('/inventory/categories/{inventoryCategory}', [InventoryCategoryController::class, 'update'])->name('inventory.categories.update');
    Route::delete('/inventory/categories/{inventoryCategory}', [InventoryCategoryController::class, 'destroy'])->name('inventory.categories.destroy');
    Route::post('/inventory/subcategories', [InventorySubcategoryController::class, 'store'])->name('inventory.subcategories.store');
    Route::patch('/inventory/subcategories/{inventorySubcategory}', [InventorySubcategoryController::class, 'update'])->name('inventory.subcategories.update');
    Route::delete('/inventory/subcategories/{inventorySubcategory}', [InventorySubcategoryController::class, 'destroy'])->name('inventory.subcategories.destroy');

    Route::post('/inventory/units', [InventoryUnitController::class, 'store'])->name('inventory.units.store');
    Route::patch('/inventory/units/{inventoryUnit}', [InventoryUnitController::class, 'update'])->name('inventory.units.update');
    Route::delete('/inventory/units/{inventoryUnit}', [InventoryUnitController::class, 'destroy'])->name('inventory.units.destroy');

    Route::get('/inventory/{inventoryItem}', [InventoryController::class, 'show'])->name('inventory.show');
    Route::patch('/inventory/{inventoryItem}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::post('/inventory/{inventoryItem}/adjustments', [InventoryStockAdjustmentController::class, 'store'])
        ->name('inventory.adjustments.store');

    Route::get('/bank-reconciliation', [BankReconciliationController::class, 'index'])->name('bank-reconciliation.index');
    Route::post('/bank-reconciliation/import', [BankReconciliationController::class, 'import'])->name('bank-reconciliation.import');
    Route::post('/bank-reconciliation/withdrawals', [BankReconciliationController::class, 'storeWithdrawal'])->name('bank-reconciliation.withdrawals.store');
    Route::post('/bank-reconciliation/withdrawals/{withdrawal}/matches', [BankReconciliationController::class, 'storeWithdrawalMatches'])->name('bank-reconciliation.withdrawals.matches.store');
    Route::post('/bank-reconciliation/withdrawals/{withdrawal}/reconcile', [BankReconciliationController::class, 'finalizeWithdrawal'])->name('bank-reconciliation.withdrawals.reconcile');
    Route::get('/bank-reconciliation/withdrawals/{withdrawal}', [BankReconciliationController::class, 'withdrawalDetails'])->name('bank-reconciliation.withdrawals.show');
    Route::post('/bank-reconciliation/reconcile', [BankReconciliationController::class, 'reconcile'])->name('bank-reconciliation.reconcile');
    Route::get('/disbursements', [DisbursementController::class, 'index'])->name('disbursements.index');
    Route::post('/disbursements', [DisbursementController::class, 'store'])->name('disbursements.store');
    Route::get('/disbursements/{disbursement}', [DisbursementController::class, 'show'])->name('disbursements.show');
    Route::patch('/disbursements/{disbursement}/approve', [DisbursementController::class, 'approve'])->name('disbursements.approve');
    Route::patch('/disbursements/{disbursement}/reject', [DisbursementController::class, 'reject'])->name('disbursements.reject');
    Route::patch('/disbursements/{disbursement}/void', [DisbursementController::class, 'voidDisbursement'])->name('disbursements.void');
    Route::patch('/contributions/{contribution}/void', [DisbursementController::class, 'voidContribution'])->name('contributions.void');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/financial-ledgers/csv', [ReportController::class, 'financialLedgersCsv'])->name('reports.financial-ledgers.csv');
    Route::get('/reports/financial-ledgers/pdf', [ReportController::class, 'financialLedgersPdf'])->name('reports.financial-ledgers.pdf');
    Route::get('/reports/philanthropy-roster/pdf', [ReportController::class, 'philanthropyRosterPdf'])->name('reports.philanthropy-roster.pdf');
    Route::get('/system-security', [SystemSecurityController::class, 'index'])
        ->middleware('role:admin')
        ->name('system-security.index');
    Route::patch('/system-security/users/{user}/two-factor-enforcement', [SystemSecurityController::class, 'updateTwoFactorEnforcement'])
        ->middleware('role:admin')
        ->name('system-security.two-factor-enforcement.update');
    Route::post('/system-security/users', [App\Http\Controllers\StaffAccountController::class, 'store'])
        ->middleware('role:admin')
        ->name('system-security.users.store');
    Route::patch('/system-security/users/{user}', [App\Http\Controllers\StaffAccountController::class, 'update'])
        ->middleware('role:admin')
        ->name('system-security.users.update');
    Route::patch('/system-security/users/{user}/deactivate', [App\Http\Controllers\StaffAccountController::class, 'deactivate'])
        ->middleware('role:admin')
        ->name('system-security.users.deactivate');
});
