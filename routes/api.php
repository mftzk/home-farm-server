<?php

use App\Http\Controllers\Api\ReadingController;
use App\Http\Controllers\Api\RelayController;
use App\Http\Controllers\Api\TemperatureController;
use Illuminate\Support\Facades\Route;

Route::get('/readings', [ReadingController::class, 'index']);
Route::get('/temperature', [TemperatureController::class, 'index']);

Route::prefix('relay')->group(function () {
    Route::get('/status', [RelayController::class, 'status']);
    Route::post('/toggle', [RelayController::class, 'toggle']);
    Route::post('/all', [RelayController::class, 'all']);
});
