<?php

use App\Http\Middleware\SetRequestLocale;
use App\Http\Middleware\StatefulFrontendRequests;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [SetRequestLocale::class, StatefulFrontendRequests::class]);
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->expectsJson() || ! $request->is('api/*', 'oauth/*')) {
                return null;
            }

            $key = match (true) {
                $exception instanceof AuthenticationException => 'authenticationRequired',
                $exception instanceof TooManyRequestsHttpException => 'retryLater',
                $exception instanceof NotFoundHttpException
                    && ($exception->getMessage() === '' || $exception->getPrevious() instanceof ModelNotFoundException) => 'notFound',
                $exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 419 => 'pageExpired',
                default => null,
            };
            if ($key === null) {
                return null;
            }

            return response()->json(['message' => __('app.'.$key)],
                $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 401,
                $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : []);
        });
    })->create();
