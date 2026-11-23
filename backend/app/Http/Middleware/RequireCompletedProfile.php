<?php

namespace App\Http\Middleware;

use App\Support\GoogleOAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCompletedProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        $persona = $request->user('sanctum');
        $pending = GoogleOAuth::pending($request);

        if ($persona?->profile_completed === false || ($pending['purpose'] ?? null) === 'role') {
            return response()->json([
                'code' => 'profile_incomplete',
                'message' => __('app.profileIncomplete'),
            ], 403)->header('Cache-Control', 'no-store');
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
