<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTenantRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $tenantId = app(CurrentTenant::class)->requireId();
        $membership = $user?->tenants()->where('tenants.id', $tenantId)->first();
        $role = $membership?->pivot?->role;

        abort_unless($role && in_array($role, $roles, true), 403, 'Your workspace role cannot perform this action.');

        return $next($request);
    }
}
