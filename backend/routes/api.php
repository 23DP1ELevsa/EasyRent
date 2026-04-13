<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\TransportController;
use App\Http\Controllers\TransportVeidsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RezervacijaController;
use App\Http\Controllers\AtsauksmeController;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('/contact', [ContactController::class, 'send'])->middleware('throttle:5,1');

Route::prefix('transport')->group(function () {
    Route::get('/', [TransportController::class, 'index']);
    Route::get('/veidi', [TransportVeidsController::class, 'index']);
    Route::get('/{id}', [TransportController::class, 'show']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('transport')->group(function () {
        Route::post('/', [TransportController::class, 'store']);
        Route::post('/veidi', [TransportVeidsController::class, 'store']);
        Route::put('/veidi/{id}', [TransportVeidsController::class, 'update']);
        Route::put('/{id}', [TransportController::class, 'update']);
        Route::delete('/{id}', [TransportController::class, 'destroy']);
    });

    Route::prefix('profile')->group(function () {
        Route::get('/{personaId}', [ProfileController::class, 'show']);
        Route::put('/{personaId}', [ProfileController::class, 'update']);
    });

    Route::prefix('rezervacijas')->group(function () {
        Route::get('/', [RezervacijaController::class, 'index']);
        Route::post('/', [RezervacijaController::class, 'store']);
        Route::post('/{id}/pay', [RezervacijaController::class, 'pay']);
        Route::delete('/{id}', [RezervacijaController::class, 'destroy']);
    });

    Route::prefix('atsauksmes')->group(function () {
        Route::post('/', [AtsauksmeController::class, 'store']);
        Route::put('/{id}', [AtsauksmeController::class, 'update']);
    });
});

// Auth API (JSON)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
});

// Atsauksmes
Route::prefix('atsauksmes')->group(function () {
    Route::get('/', [AtsauksmeController::class, 'index']);
});