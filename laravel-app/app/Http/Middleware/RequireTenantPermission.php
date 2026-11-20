<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTenantPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        $tenantId = app(CurrentTenant::class)->requireId();
        $membership = $user?->tenants()->where('tenants.id', $tenantId)->first();
        $roleId = $membership?->pivot?->role_id;

        abort_unless($roleId && $permissions !== [], 403, 'A workspace role with the required permission is needed.');

        $allowed = Role::query()
            ->whereKey($roleId)
            ->whereHas('permissions', fn ($query) => $query->whereIn('permissions.code', $permissions))
            ->exists();

        abort_unless($allowed, 403, 'Your role does not have permission to perform this action.');

        return $next($request);
    }
}
