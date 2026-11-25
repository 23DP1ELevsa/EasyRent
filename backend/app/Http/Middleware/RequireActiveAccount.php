<?php

namespace App\Http\Middleware;

use App\Support\AccountRestrictions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $error = $request->user() ? AccountRestrictions::authenticationError($request, $request->user()) : null;

        return $error ?? $next($request);
    }
}
