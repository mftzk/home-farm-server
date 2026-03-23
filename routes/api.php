<?php

use App\Http\Controllers\Api\ReadingController;
use Illuminate\Support\Facades\Route;

Route::get('/readings', [ReadingController::class, 'index']);
