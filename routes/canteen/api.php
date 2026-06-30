<?php

use App\Modules\Canteen\Http\Controllers\PosApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('canteen.permission:canteen.pos.access')->group(function () {
    Route::get('/products', [PosApiController::class, 'products'])->name('products');
    Route::get('/barcode/{code}', [PosApiController::class, 'barcode'])->name('barcode');
    Route::get('/students/search', [PosApiController::class, 'searchStudents'])->name('students.search');
    Route::get('/students/{ref}/eligibility', [PosApiController::class, 'eligibility'])->name('students.eligibility');
    Route::get('/students/{ref}/product-block', [PosApiController::class, 'checkProductBlock'])->name('students.product-block');
    Route::post('/cart/validate', [PosApiController::class, 'validateCart'])->name('cart.validate');
    Route::post('/sales', [PosApiController::class, 'storeSale'])->name('sales.store');
    Route::get('/sales/today', [PosApiController::class, 'today'])->name('sales.today');
});
