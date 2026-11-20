<?php

use App\Http\Middleware\EnforcePlanLimit;
use App\Http\Middleware\RequireActiveSubscriptionForWrites;
use App\Http\Middleware\RequirePlatformAdmin;
use App\Http\Middleware\RequireTenantPermission;
use App\Http\Middleware\RequireTenantRole;
use App\Http\Middleware\ResolveTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Allow the PC web dashboard to use the same Sanctum-protected API via session cookies.
        // Flutter uses Bearer tokens and is not dependent on browser cookies.
        $middleware->statefulApi();
        $middleware->alias([
            'tenant.context' => ResolveTenantContext::class,
            'tenant.role' => RequireTenantRole::class,
            'tenant.permission' => RequireTenantPermission::class,
            'subscription.writes' => RequireActiveSubscriptionForWrites::class,
            'plan.limit' => EnforcePlanLimit::class,
            'platform.admin' => RequirePlatformAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Keep framework defaults; add structured reporting in the deployment setup.
    })
    ->create();
