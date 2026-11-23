<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireVerifiedEmail
{
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $persona = $request->user();
        if ($persona->hasVerifiedEmail() || ($scope === 'onboarding' && $persona->mayCompleteGoogleProfile())) {
            return $next($request);
        }

        return self::response();
    }

    public static function response(): JsonResponse
    {
        return response()->json([
            'code' => 'email_not_verified',
            'message' => __('email-verification.required'),
        ], 403)->header('Cache-Control', 'no-store');
    }
}
