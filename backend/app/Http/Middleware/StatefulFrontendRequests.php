<?php

namespace App\Http\Middleware;

use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class StatefulFrontendRequests extends EnsureFrontendRequestsAreStateful
{
    public static function fromFrontend($request)
    {
        // Opening an API URL from the address bar sends the session cookie without a referrer.
        return parent::fromFrontend($request)
            || ($request->isMethodSafe()
                && ! $request->headers->has('Origin')
                && ! $request->headers->has('Referer')
                && $request->cookies->has(config('session.cookie')));
    }
}
