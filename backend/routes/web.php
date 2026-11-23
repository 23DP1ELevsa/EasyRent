<?php

use App\Http\Controllers\GoogleOAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('oauth/google')->group(function () {
    Route::get('/start', [GoogleOAuthController::class, 'start'])->middleware('throttle:20,1')->block(20, 20);
    Route::post('/deletion', [GoogleOAuthController::class, 'startDeletion'])->middleware('throttle:10,1')->block(20, 20);
    Route::get('/callback', [GoogleOAuthController::class, 'callback'])->middleware('throttle:30,1')->block(20, 20);
    Route::post('/role', [GoogleOAuthController::class, 'chooseRole'])->middleware('throttle:10,1')->block(20, 20);
    Route::post('/link', [GoogleOAuthController::class, 'link'])->middleware('throttle:20,1')->block(20, 20);
    Route::post('/cancel', [GoogleOAuthController::class, 'cancel'])->block(20, 20);
});
