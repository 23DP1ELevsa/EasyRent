<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TransportController;
use App\Http\Controllers\TransportVeidsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RezervacijaController;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::prefix('transport')->group(function () {
    Route::get('/', [TransportController::class, 'index']);
    Route::post('/', [TransportController::class, 'store']);
    Route::get('/veidi', [TransportVeidsController::class, 'index']);
    Route::post('/veidi', [TransportVeidsController::class, 'store']);
    Route::get('/{id}', [TransportController::class, 'show']);
    Route::put('/{id}', [TransportController::class, 'update']);
});

// Auth API (JSON)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
});

// Profile API
Route::prefix('profile')->group(function () {
    Route::get('/{personaId}', [ProfileController::class, 'show']);
    Route::put('/{personaId}', [ProfileController::class, 'update']);
});

// Rezervācijas un apmaksa
Route::prefix('rezervacijas')->group(function () {
    Route::get('/', [RezervacijaController::class, 'index']);
    Route::post('/', [RezervacijaController::class, 'store']);
    Route::post('/{id}/pay', [RezervacijaController::class, 'pay']);
    Route::delete('/{id}', [RezervacijaController::class, 'destroy']);
});