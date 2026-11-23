<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8)->mixedCase()->symbols());

        RateLimiter::for('password-reset-request', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('password-reset-submit', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('email-verification-confirm', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('email-verification-resend', fn (Request $request) => [
            Limit::perMinute(5)->by('minute:'.$request->ip()),
            Limit::perHour(30)->by('hour:'.$request->ip()),
        ]);
    }
}
