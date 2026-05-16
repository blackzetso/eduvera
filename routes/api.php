<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryApiController;
use App\Http\Controllers\Api\RegisterApiController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [RegisterApiController::class, 'register']);
Route::get('/categories', [RegisterApiController::class, 'index']);
Route::get('/categories/{id}/children', [RegisterApiController::class, 'children']);
