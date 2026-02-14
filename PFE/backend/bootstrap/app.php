<?php

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
        ]);
        $middleware->api(append: [\App\Http\Middleware\SecurityHeaders::class]);
        // API: never redirect to login; return 401 JSON instead (handled by renderable below)
        Authenticate::redirectUsing(function ($request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }
            return \Illuminate\Support\Facades\Route::has('login') ? route('login') : null;
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Non authentifié.'], 401);
            }
        });
    })->create();
