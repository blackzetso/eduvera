<?php

use App\Modules\Canteen\Http\Controllers\AuditController;
use App\Modules\Canteen\Http\Controllers\CategoryController;
use App\Modules\Canteen\Http\Controllers\DashboardController;
use App\Modules\Canteen\Http\Controllers\InventoryController;
use App\Modules\Canteen\Http\Controllers\PosController;
use App\Modules\Canteen\Http\Controllers\ProductController;
use App\Modules\Canteen\Http\Controllers\ReportController;
use App\Modules\Canteen\Http\Controllers\SettingsController;
use App\Modules\Canteen\Http\Controllers\StudentLimitController;
use App\Modules\Canteen\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/'.config('canteen.module.route_prefix', 'canteen').'/dashboard');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('canteen.permission:canteen.dashboard.view')
    ->name('dashboard');

Route::get('/pos', [PosController::class, 'index'])
    ->middleware('canteen.permission:canteen.pos.access')
    ->name('pos');

Route::middleware('canteen.permission:canteen.categories.manage')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
});

Route::middleware('canteen.permission:canteen.products.view')->group(function () {
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])
        ->middleware('canteen.permission:canteen.products.manage')
        ->name('products.create');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])
        ->middleware('canteen.permission:canteen.products.manage')
        ->name('products.edit');
});

Route::middleware('canteen.permission:canteen.products.manage')->group(function () {
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
});

Route::middleware('canteen.permission:canteen.inventory.view')->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/{product}/ledger', [InventoryController::class, 'ledger'])->name('inventory.ledger');
});

Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])
    ->middleware('canteen.permission:canteen.inventory.manage')
    ->name('inventory.adjust');

Route::middleware('canteen.permission:canteen.transactions.view')->group(function () {
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
});

Route::post('/transactions/{transaction}/void', [TransactionController::class, 'void'])
    ->middleware('canteen.permission:canteen.transactions.void')
    ->name('transactions.void');

Route::middleware('canteen.permission:canteen.student-limits.manage')->group(function () {
    Route::get('/student-limits', [StudentLimitController::class, 'index'])->name('student-limits.index');
    Route::post('/student-limits/profiles', [StudentLimitController::class, 'storeProfile'])->name('student-limits.profiles.store');
    Route::put('/student-limits/profiles/{profile}', [StudentLimitController::class, 'updateProfile'])->name('student-limits.profiles.update');
    Route::post('/student-limits/restrictions', [StudentLimitController::class, 'assignRestriction'])->name('student-limits.restrictions.assign');
    Route::delete('/student-limits/restrictions/{assignment}', [StudentLimitController::class, 'removeRestriction'])->name('student-limits.restrictions.destroy');
    Route::post('/student-limits/blocked-products', [StudentLimitController::class, 'storeBlockedProduct'])->name('student-limits.blocked-products.store');
    Route::delete('/student-limits/blocked-products/{studentBlockedProduct}', [StudentLimitController::class, 'removeBlockedProduct'])->name('student-limits.blocked-products.destroy');
    Route::post('/student-limits/blocked-categories', [StudentLimitController::class, 'storeBlockedCategory'])->name('student-limits.blocked-categories.store');
    Route::delete('/student-limits/blocked-categories/{studentBlockedCategory}', [StudentLimitController::class, 'removeBlockedCategory'])->name('student-limits.blocked-categories.destroy');
});

Route::middleware('canteen.permission:canteen.audit.view')->group(function () {
    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    Route::get('/audit/{audit}', [AuditController::class, 'show'])->name('audit.show');
});

Route::middleware('canteen.permission:canteen.reports.view')->group(function () {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{type}/print', [ReportController::class, 'print'])->name('reports.print');
    Route::get('/reports/{type}/export', [ReportController::class, 'export'])
        ->middleware('canteen.permission:canteen.reports.export')
        ->name('reports.export');
    Route::get('/reports/{type}', [ReportController::class, 'show'])->name('reports.show');
});

Route::middleware('canteen.permission:canteen.settings.manage')->group(function () {
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/staff', [SettingsController::class, 'storeStaff'])
        ->middleware('canteen.permission:canteen.staff.manage')
        ->name('settings.staff.store');
    Route::post('/settings/rules', [SettingsController::class, 'storeRule'])->name('settings.rules.store');
    Route::put('/settings', [SettingsController::class, 'updateSettings'])->name('settings.update');
});
