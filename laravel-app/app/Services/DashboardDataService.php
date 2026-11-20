<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PrintOrder;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\Cache;

class DashboardDataService
{
    public function forUser(User $user): array
    {
        $tenantId = app(CurrentTenant::class)->requireId();

        return Cache::remember("tenant:{$tenantId}:dashboard:v2", now()->addSeconds(20), function () use ($user): array {
            $openStatuses = ['new', 'proof', 'production', 'finishing', 'ready'];
            $monthStart = now()->startOfMonth();
            $monthEnd = now()->endOfMonth();
            $today = today();
            $nextWeek = $today->copy()->addDays(7);

            $outstanding = (float) Invoice::query()
                ->whereIn('status', ['unpaid', 'partial', 'overdue'])
                ->selectRaw('COALESCE(SUM(total - paid_total), 0) AS balance')
                ->value('balance');

            $stageCounts = PrintOrder::query()
                ->select('production_stage')
                ->selectRaw('COUNT(*) AS order_count')
                ->groupBy('production_stage')
                ->pluck('order_count', 'production_stage')
                ->map(fn ($count): int => (int) $count)
                ->all();

            $dueOrders = fn () => PrintOrder::query()
                ->whereNotIn('status', ['delivered', 'cancelled'])
                ->whereNotNull('due_at');

            $dueDateCounts = [
                'overdue' => $dueOrders()->whereDate('due_at', '<', $today)->count(),
                'today' => $dueOrders()->whereDate('due_at', $today)->count(),
                'tomorrow' => $dueOrders()->whereDate('due_at', $today->copy()->addDay())->count(),
                'in_two_days' => $dueOrders()->whereDate('due_at', $today->copy()->addDays(2))->count(),
                'next_3_to_7_days' => $dueOrders()
                    ->whereDate('due_at', '>=', $today->copy()->addDays(3))
                    ->whereDate('due_at', '<=', $nextWeek)
                    ->count(),
                'over_7_days' => $dueOrders()->whereDate('due_at', '>', $nextWeek)->count(),
            ];

            return [
                'open_orders' => PrintOrder::query()->whereIn('status', $openStatuses)->count(),
                'in_production' => PrintOrder::query()->whereIn('status', ['proof', 'production', 'finishing'])->count(),
                'due_today' => $dueDateCounts['today'],
                'outstanding_balance' => round($outstanding, 2),
                'revenue_this_month' => round((float) Payment::query()->where('status', 'succeeded')
                    ->whereBetween('paid_at', [$monthStart, $monthEnd])->sum('amount'), 2),
                'currency' => $user->currentTenant?->default_currency ?? 'LKR',
                'recent_orders' => PrintOrder::query()->with('customer:id,name,phone')->latest()->limit(8)->get(),
                'overdue_invoices' => Invoice::query()->where('status', 'overdue')->count(),
                'stage_counts' => $stageCounts,
                'due_date_counts' => $dueDateCounts,
            ];
        });
    }
}
