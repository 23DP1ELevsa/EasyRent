<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TransportController;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::prefix('transport')->group(function () {
    Route::get('/', [TransportController::class, 'index']);
    Route::get('/{id}', [TransportController::class, 'show']);
});