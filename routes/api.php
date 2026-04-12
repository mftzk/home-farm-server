<?php

use App\Http\Controllers\Api\InsightController;
use App\Http\Controllers\Api\ModeController;
use App\Http\Controllers\Api\ReadingController;
use App\Http\Controllers\Api\RelayController;
use App\Http\Controllers\Api\TemperatureController;
use App\Http\Middleware\RequireEditMode;
use Illuminate\Support\Facades\Route;

Route::get('/readings', [ReadingController::class, 'index']);
Route::get('/temperature', [TemperatureController::class, 'index']);
Route::get('/insight/daily', [InsightController::class, 'daily']);

Route::prefix('relay')->group(function () {
    Route::get('/status', [RelayController::class, 'status']);
    Route::get('/auto-config', [RelayController::class, 'autoConfig']);

    Route::middleware(RequireEditMode::class)->group(function () {
        Route::post('/toggle', [RelayController::class, 'toggle']);
        Route::post('/all', [RelayController::class, 'all']);
        Route::post('/auto-config', [RelayController::class, 'updateAutoConfig']);
    });
});

Route::prefix('mode')->group(function () {
    Route::get('/status', [ModeController::class, 'status']);
    Route::post('/verify', [ModeController::class, 'verify'])->middleware('throttle:5,1');
    Route::post('/lock', [ModeController::class, 'lock']);
});
