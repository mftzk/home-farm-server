<?php

use App\Http\Controllers\Api\InsightController;
use App\Http\Controllers\Api\ReadingController;
use App\Http\Controllers\Api\RelayController;
use App\Http\Controllers\Api\TemperatureController;
use Illuminate\Support\Facades\Route;

Route::get('/readings', [ReadingController::class, 'index']);
Route::get('/temperature', [TemperatureController::class, 'index']);
Route::get('/insight/daily', [InsightController::class, 'daily']);

Route::prefix('relay')->group(function () {
    Route::get('/status', [RelayController::class, 'status']);
    Route::post('/toggle', [RelayController::class, 'toggle']);
    Route::post('/all', [RelayController::class, 'all']);
    Route::get('/auto-config', [RelayController::class, 'autoConfig']);
    Route::post('/auto-config', [RelayController::class, 'updateAutoConfig']);
});
