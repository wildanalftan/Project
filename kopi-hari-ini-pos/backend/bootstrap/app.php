<?php

use App\Http\Middleware\CheckRole;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api:         __DIR__ . '/../routes/api.php',
        apiPrefix:   'api',
        web:         __DIR__ . '/../routes/web.php',
        commands:    __DIR__ . '/../routes/console.php',
        health:      '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register alias for role-based access control
        $middleware->alias([
            'role' => CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return JSON for all API authentication errors
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Tidak terautentikasi.'], 401);
            }
        });

        // Return JSON for validation errors on API routes
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors'  => $e->errors(),
                ], 422);
            }
        });
    })->create();
