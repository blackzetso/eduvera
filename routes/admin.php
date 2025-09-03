<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\admin\DashboardController;
use App\Http\Controllers\admin\FormbuilderController;
use Illuminate\Support\Facades\Route;



Route::middleware(['auth', 'verified'])->as('admin.')->prefix('admin')->group(function () {

    route::get('dashboard',[DashboardController::class,'index'])->name('admin.dashboard');
    Route::resource('forms',FormbuilderController::class);

});


require __DIR__.'/auth.php';
