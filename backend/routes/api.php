<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\TransportController;
use App\Http\Controllers\TransportVeidsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountDeletionController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RezervacijaController;
use App\Http\Controllers\AtsauksmeController;
use App\Http\Controllers\AdminDataController;
use App\Http\Controllers\AdminModerationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\InvoiceRecoveryController;
use App\Http\Controllers\VehiclePhotoController;
use App\Http\Middleware\RequireActiveAccount;
use App\Http\Middleware\RequireAdministrator;
use App\Http\Middleware\RequireCompletedProfile;
use App\Http\Middleware\RequireVerifiedEmail;
use App\Http\Middleware\VerifyTurnstile;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('/contact', [ContactController::class, 'send'])->middleware(['throttle:5,1', VerifyTurnstile::class.':contact']);
Route::post('/support/invoice', [InvoiceRecoveryController::class, 'send'])->middleware(['throttle:5,1', VerifyTurnstile::class.':invoice_recovery']);

Route::get('/transport/{id}/public-photos', [VehiclePhotoController::class, 'publicIndex'])->whereNumber('id');
Route::get('/transport/{id}/public-photos/{photo}', [VehiclePhotoController::class, 'publicShow'])
    ->whereNumber(['id', 'photo'])->name('transport.public-photos.show');

Route::middleware(RequireCompletedProfile::class)->group(function () {

    Route::prefix('transport')->group(function () {
        Route::get('/', [TransportController::class, 'index']);
        Route::get('/veidi', [TransportVeidsController::class, 'index']);
        Route::get('/{id}', [TransportController::class, 'show'])->whereNumber('id');
    });

    Route::get('/atsauksmes', [AtsauksmeController::class, 'index']);
});

Route::middleware(['auth:sanctum', RequireActiveAccount::class, RequireVerifiedEmail::class, RequireCompletedProfile::class])->group(function () {
    Route::prefix('transport')->group(function () {
        Route::post('/', [TransportController::class, 'store']);
        Route::put('/{id}', [TransportController::class, 'update']);
        Route::delete('/{id}', [TransportController::class, 'destroy']);
        Route::get('/{id}/photos', [VehiclePhotoController::class, 'index'])->whereNumber('id');
        Route::get('/{id}/photos/{photo}', [VehiclePhotoController::class, 'show'])->whereNumber(['id', 'photo']);
        Route::post('/{id}/photos', [VehiclePhotoController::class, 'store'])->middleware('throttle:10,1')->whereNumber('id');
        Route::post('/{id}/photos/{photo}', [VehiclePhotoController::class, 'replace'])->middleware('throttle:10,1')->whereNumber(['id', 'photo']);
        Route::patch('/{id}/photos/{photo}/primary', [VehiclePhotoController::class, 'makePrimary'])->whereNumber(['id', 'photo']);
        Route::delete('/{id}/photos/{photo}', [VehiclePhotoController::class, 'destroy'])->whereNumber(['id', 'photo']);
    });

    Route::prefix('rezervacijas')->group(function () {
        Route::get('/', [RezervacijaController::class, 'index']);
        Route::post('/', [RezervacijaController::class, 'store']);
        Route::post('/{id}/pay', [RezervacijaController::class, 'pay']);
        Route::get('/{id}/invoice', [RezervacijaController::class, 'invoice'])->whereNumber('id');
        Route::delete('/{id}', [RezervacijaController::class, 'destroy']);
    });

    Route::post('/reports', [ReportController::class, 'store'])->middleware(['throttle:5,1', VerifyTurnstile::class.':report']);

    Route::prefix('atsauksmes')->group(function () {
        Route::post('/', [AtsauksmeController::class, 'store']);
        Route::put('/{id}', [AtsauksmeController::class, 'update']);
    });
});

Route::prefix('profile')->middleware(['auth:sanctum', RequireActiveAccount::class])->group(function () {
    Route::get('/deletion-check', [AccountDeletionController::class, 'check'])->middleware(RequireVerifiedEmail::class);
    Route::delete('/me', [AccountDeletionController::class, 'destroy'])->middleware([RequireVerifiedEmail::class, 'throttle:5,1']);
    Route::get('/{personaId}', [ProfileController::class, 'show'])->whereNumber('personaId')->middleware(RequireVerifiedEmail::class.':onboarding');
    Route::put('/{personaId}', [ProfileController::class, 'update'])->whereNumber('personaId')->middleware(RequireVerifiedEmail::class.':onboarding');
});

Route::middleware(['auth:sanctum', RequireVerifiedEmail::class, RequireAdministrator::class, RequireActiveAccount::class])->group(function () {
    // Keep the established write URLs restricted to verified administrators.
    Route::post('/transport/veidi', [TransportVeidsController::class, 'store']);
    Route::put('/transport/veidi/{id}', [TransportVeidsController::class, 'update'])->whereNumber('id');

    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDataController::class, 'dashboard']);
        foreach (['users', 'vehicles', 'categories', 'bookings', 'payments', 'reviews', 'reports', 'audit'] as $resource) {
            Route::get('/'.$resource, [AdminDataController::class, $resource]);
        }
        Route::get('/users/{id}', [AdminDataController::class, 'user'])->whereNumber('id');
        Route::get('/vehicles/{id}', [AdminDataController::class, 'vehicle'])->whereNumber('id');
        Route::get('/vehicles/{id}/photos', [VehiclePhotoController::class, 'index'])->whereNumber('id')->name('admin.vehicle.photos.index');
        Route::get('/vehicles/{id}/photos/{photo}', [VehiclePhotoController::class, 'show'])->whereNumber(['id', 'photo'])->name('admin.vehicle.photos.show');
        Route::get('/reports/{id}', [AdminDataController::class, 'report'])->whereNumber('id');
        Route::post('/users/{id}/restrictions', [AdminModerationController::class, 'restrict'])->whereNumber('id');
        Route::patch('/users/{id}/restrictions/{restrictionId}', [AdminModerationController::class, 'revoke'])->whereNumber(['id', 'restrictionId']);
        Route::post('/categories', [TransportVeidsController::class, 'store']);
        Route::put('/categories/{id}', [TransportVeidsController::class, 'update'])->whereNumber('id');
        Route::patch('/categories/{id}/availability', [TransportVeidsController::class, 'availability'])->whereNumber('id');
        Route::patch('/vehicles/{id}/visibility', [AdminModerationController::class, 'vehicle'])->whereNumber('id');
        Route::patch('/reviews/{id}/visibility', [AdminModerationController::class, 'review'])->whereNumber('id');
        Route::patch('/reports/{id}', [AdminModerationController::class, 'report'])->whereNumber('id');
    });
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware(['throttle:10,1', VerifyTurnstile::class.':register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware(['throttle:10,1', 'throttle:password-login-ip']);
    Route::post('/forgot-password', [PasswordResetController::class, 'requestLink'])->middleware(['throttle:password-reset-request', VerifyTurnstile::class.':forgot_password']);
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset-submit');
    Route::post('/verification/confirm', [EmailVerificationController::class, 'confirm'])->middleware('throttle:email-verification-confirm');
    Route::post('/verification/resend', [EmailVerificationController::class, 'resend'])->middleware(['throttle:email-verification-resend', VerifyTurnstile::class.':verification_resend']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});
