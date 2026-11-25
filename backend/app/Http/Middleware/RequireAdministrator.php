<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->loma === 'administrators', 403, __('admin.forbidden'));

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
