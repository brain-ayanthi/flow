<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveSubscriptionForWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allow reads and subscription-management endpoints so an owner can renew.
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
            || $request->routeIs('api.v1.subscription.*')) {
            return $next($request);
        }

        $tenantId = app(CurrentTenant::class)->requireId();
        $now = now();

        $hasAccess = Subscription::query()
            ->where(function ($query) use ($now): void {
                $query->where(function ($active) use ($now): void {
                    $active->where('status', 'active')
                        ->where(function ($period) use ($now): void {
                            $period->whereNull('current_period_ends_at')
                                ->orWhere('current_period_ends_at', '>', $now);
                        });
                })->orWhere(function ($trial) use ($now): void {
                    $trial->where('status', 'trialing')
                        ->whereNotNull('trial_ends_at')
                        ->where('trial_ends_at', '>', $now);
                });
            })
            ->exists();

        if (! $hasAccess) {
            return response()->json([
                'message' => 'An active subscription is required to create or change records.',
                'code' => 'subscription_required',
                'tenant_id' => $tenantId,
            ], 402);
        }

        return $next($request);
    }
}
