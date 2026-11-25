<?php

namespace App\Http\Middleware;

use App\Support\TurnstileService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTurnstile
{
    public function __construct(private TurnstileService $turnstile) {}

    public function handle(Request $request, Closure $next, string $action): Response
    {
        $this->turnstile->verify($request, $action);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
