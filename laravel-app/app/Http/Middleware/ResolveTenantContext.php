<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401, 'Authentication required.');

        $tenantId = $user->current_tenant_id;
        abort_unless($tenantId, 403, 'Select a workspace first.');

        // The tenant must be a workspace the authenticated user belongs to.
        $isMember = $user->tenants()->whereKey($tenantId)->exists();
        abort_unless($isMember, 403, 'You do not have access to this workspace.');

        app(CurrentTenant::class)->set((int) $tenantId);

        return $next($request);
    }
}
