<?php

use App\Http\Controllers\Api\FormRuntimeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryApiController;
use App\Http\Controllers\Api\RegisterApiController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/admissions/intake/visit', [\App\Http\Controllers\Api\AdmissionIntakeController::class, 'visit'])
    ->middleware([
        'admission-intake',
        'throttle:'.config('admissions_intake.rate_limit_per_minute', 10).',1',
    ]);

Route::post('/register', [RegisterApiController::class, 'register']);
Route::get('/categories', [RegisterApiController::class, 'index']);
Route::get('/categories/{id}/children', [RegisterApiController::class, 'children']);

Route::middleware(['card-reader'])->prefix('v1')->group(function () {
    Route::post('/attendance/card-scan', [\App\Http\Controllers\Api\AttendanceCardController::class, 'scan']);
});

Route::prefix('forms/{form}')->group(function () {
    Route::get('runtime', [FormRuntimeController::class, 'runtime'])
        ->middleware('throttle:form-runtime-get');

    Route::post('submissions', [FormRuntimeController::class, 'store'])
        ->middleware('throttle:form-submission-post');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('submissions', [FormRuntimeController::class, 'index'])
            ->middleware('throttle:form-submission-list');

        Route::get('submissions/{submission}', [FormRuntimeController::class, 'show'])
            ->middleware('throttle:form-submission-read');

        Route::patch('submissions/{submission}/status', [FormRuntimeController::class, 'updateStatus'])
            ->middleware('throttle:form-submission-review');
    });
});
