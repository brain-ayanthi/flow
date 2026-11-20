<?php

namespace App\Http\Middleware;

use App\Models\PrintOrder;
use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePlanLimit
{
    public function handle(Request $request, Closure $next, string $limitKey): Response
    {
        $subscription = Subscription::query()
            ->with('plan:id,limits')
            ->whereIn('status', ['active', 'trialing'])
            ->latest('id')
            ->first();

        if (! $subscription) {
            return $next($request); // subscription.writes returns the more useful 402 response
        }

        $limits = $subscription->plan_snapshot['limits'] ?? $subscription->plan?->limits ?? [];
        $limit = $limits[$limitKey] ?? null;

        // null means unlimited/not configured; zero is a real zero-capacity limit.
        if ($limit === null) {
            return $next($request);
        }

        $usage = match ($limitKey) {
            'orders_per_month' => $this->ordersInCurrentPeriod($subscription),
            default => 0,
        };

        if ($usage >= (int) $limit) {
            return response()->json([
                'message' => 'The current plan limit has been reached. Upgrade the plan to continue.',
                'code' => 'plan_limit_reached',
                'limit' => $limitKey,
                'maximum' => (int) $limit,
                'used' => $usage,
            ], 402);
        }

        return $next($request);
    }

    private function ordersInCurrentPeriod(Subscription $subscription): int
    {
        $start = $subscription->current_period_starts_at ?? $subscription->created_at;

        return PrintOrder::query()->where('created_at', '>=', $start)->count();
    }
}
