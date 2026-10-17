<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'password.change' => \App\Http\Middleware\EnsurePasswordChanged::class,
            'workspace' => \App\Http\Middleware\ResolveActiveWorkspace::class,
            'workspace.required' => \App\Http\Middleware\RequireActiveWorkspace::class,
            'workspace.models' => \App\Http\Middleware\EnsureWorkspaceRouteModels::class,
            'platform.superadmin' => \App\Http\Middleware\RequirePlatformSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
