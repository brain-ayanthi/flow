<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'starter',
                'name' => 'Starter',
                'description' => 'For a growing print shop getting organised.',
                'monthly_price' => 2900,
                'yearly_price' => 29000,
                'trial_days' => 14,
                'limits' => ['orders_per_month' => 80, 'team_members' => 3, 'branches' => 1, 'storage_mb' => 2048],
                'features' => ['customers', 'orders', 'production', 'invoices', 'basic_reports'],
                'sort_order' => 10,
            ],
            [
                'code' => 'business',
                'name' => 'Business',
                'description' => 'More capacity for a busy production team.',
                'monthly_price' => 6900,
                'yearly_price' => 69000,
                'trial_days' => 0,
                'limits' => ['orders_per_month' => 300, 'team_members' => 10, 'branches' => 1, 'storage_mb' => 10240],
                'features' => ['customers', 'orders', 'production', 'invoices', 'reports', 'job_sheets'],
                'sort_order' => 20,
            ],
            [
                'code' => 'studio',
                'name' => 'Studio',
                'description' => 'Advanced controls for multiple branches.',
                'monthly_price' => 14900,
                'yearly_price' => 149000,
                'trial_days' => 0,
                'limits' => ['orders_per_month' => null, 'team_members' => 25, 'branches' => 5, 'storage_mb' => 51200],
                'features' => ['customers', 'orders', 'production', 'invoices', 'reports', 'job_sheets', 'multi_branch', 'advanced_roles'],
                'sort_order' => 30,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::query()->updateOrCreate(['code' => $plan['code']], $plan + ['is_active' => true]);
        }
    }
}
