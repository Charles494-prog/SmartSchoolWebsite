<?php

use App\Http\Middleware\AccountGuard;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => RoleMiddleware::class]);
        $middleware->prepend(RequestId::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->appendToGroup('web', AccountGuard::class);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Never leak internals: JSON errors get a message + request ID only.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->expectsJson() || config('app.debug')
                || $e instanceof ValidationException || $e instanceof AuthenticationException) {
                return null;
            }
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            return response()->json([
                'message' => $status >= 500 ? 'Something went wrong. Please try again.' : ($e->getMessage() ?: 'Request not allowed.'),
                'request_id' => $request->attributes->get('request_id'),
            ], $status);
        });
    })->create();
