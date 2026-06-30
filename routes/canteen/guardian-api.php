<?php

use App\Modules\Canteen\Http\Controllers\Guardian\CanteenDashboardApiController;
use App\Modules\Canteen\Http\Controllers\Guardian\StudentLimitsApiController;
use Illuminate\Support\Facades\Route;

Route::get('/summary', [CanteenDashboardApiController::class, 'summary'])->name('summary');

Route::prefix('children/{student}')
    ->middleware('canteen.guardian.owns-student')
    ->group(function () {
        Route::get('/limits', [StudentLimitsApiController::class, 'showLimits'])->name('children.limits.show');
        Route::put('/daily-limit', [StudentLimitsApiController::class, 'updateDailyLimit'])->name('children.limits.daily');
        Route::put('/purchase-blocked', [StudentLimitsApiController::class, 'updatePurchaseBlocked'])->name('children.limits.purchase-blocked');
        Route::put('/health-restrictions', [StudentLimitsApiController::class, 'updateHealthRestrictions'])->name('children.limits.health');

        Route::get('/blocks', [StudentLimitsApiController::class, 'blocks'])->name('children.blocks.index');
        Route::post('/blocked-products', [StudentLimitsApiController::class, 'storeBlockedProduct'])->name('children.blocks.products.store');
        Route::delete('/blocked-products/{studentBlockedProduct}', [StudentLimitsApiController::class, 'destroyBlockedProduct'])->name('children.blocks.products.destroy');
        Route::post('/blocked-categories', [StudentLimitsApiController::class, 'storeBlockedCategory'])->name('children.blocks.categories.store');
        Route::delete('/blocked-categories/{studentBlockedCategory}', [StudentLimitsApiController::class, 'destroyBlockedCategory'])->name('children.blocks.categories.destroy');

        Route::get('/purchases', [CanteenDashboardApiController::class, 'purchases'])->name('children.purchases.index');
        Route::get('/purchases/{sale}', [CanteenDashboardApiController::class, 'showPurchase'])->name('children.purchases.show');
        Route::get('/spending', [CanteenDashboardApiController::class, 'spending'])->name('children.spending');
    });
