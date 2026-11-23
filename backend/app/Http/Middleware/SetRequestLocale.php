<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetRequestLocale
{
    private const LANGUAGES = ['lv', 'ru', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $requested = match (true) {
            $request->is('oauth/google/start') => $request->query('lang'),
            $request->is('api/auth/forgot-password', 'api/auth/verification/resend') => $request->input('locale'),
            default => null,
        };
        $locale = in_array($requested, self::LANGUAGES, true)
            ? $requested
            : $request->getPreferredLanguage(self::LANGUAGES);
        App::setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);
        $response->setVary('Accept-Language', false);

        return $response;
    }
}
