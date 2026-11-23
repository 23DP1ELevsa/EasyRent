<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\TransportController;
use App\Http\Controllers\TransportVeidsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountDeletionController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RezervacijaController;
use App\Http\Controllers\AtsauksmeController;
use App\Http\Middleware\RequireCompletedProfile;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::middleware(RequireCompletedProfile::class)->group(function () {
    Route::post('/contact', [ContactController::class, 'send'])->middleware('throttle:5,1');

    Route::prefix('transport')->group(function () {
        Route::get('/', [TransportController::class, 'index']);
        Route::get('/veidi', [TransportVeidsController::class, 'index']);
        Route::get('/{id}', [TransportController::class, 'show']);
    });

    Route::get('/atsauksmes', [AtsauksmeController::class, 'index']);
});

Route::middleware(['auth:sanctum', RequireCompletedProfile::class])->group(function () {
    Route::prefix('transport')->group(function () {
        Route::post('/', [TransportController::class, 'store']);
        Route::post('/veidi', [TransportVeidsController::class, 'store']);
        Route::put('/veidi/{id}', [TransportVeidsController::class, 'update']);
        Route::put('/{id}', [TransportController::class, 'update']);
        Route::delete('/{id}', [TransportController::class, 'destroy']);
    });

    Route::prefix('rezervacijas')->group(function () {
        Route::get('/', [RezervacijaController::class, 'index']);
        Route::post('/', [RezervacijaController::class, 'store']);
        Route::post('/{id}/pay', [RezervacijaController::class, 'pay']);
        Route::get('/{id}/invoice', [RezervacijaController::class, 'invoice'])->whereNumber('id');
        Route::delete('/{id}', [RezervacijaController::class, 'destroy']);
    });

    Route::prefix('atsauksmes')->group(function () {
        Route::post('/', [AtsauksmeController::class, 'store']);
        Route::put('/{id}', [AtsauksmeController::class, 'update']);
    });
});

Route::prefix('profile')->middleware('auth:sanctum')->group(function () {
    Route::get('/deletion-check', [AccountDeletionController::class, 'check']);
    Route::delete('/me', [AccountDeletionController::class, 'destroy'])->middleware('throttle:5,1');
    Route::get('/{personaId}', [ProfileController::class, 'show'])->whereNumber('personaId');
    Route::put('/{personaId}', [ProfileController::class, 'update'])->whereNumber('personaId');
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/forgot-password', [PasswordResetController::class, 'requestLink'])->middleware('throttle:password-reset-request');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset-submit');
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});
