<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerContactApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Contact Test Shop',
            'slug' => 'contact-test-shop',
            'default_currency' => 'LKR',
            'default_locale' => 'en',
        ]);

        $owner = User::factory()->create();
        $owner->forceFill(['current_tenant_id' => $this->tenant->id])->save();
        $this->tenant->users()->attach($owner->id, ['role' => 'owner']);
        Sanctum::actingAs($owner);
        app(CurrentTenant::class)->set((int) $this->tenant->id);

        $plan = SubscriptionPlan::query()->create([
            'code' => 'contact-test-plan',
            'name' => 'Contact test plan',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'trial_days' => 14,
            'limits' => ['orders_per_month' => 20],
            'features' => [],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Subscription::query()->create([
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'billing_period' => 'monthly',
            'price_at_signup' => 0,
            'currency' => 'LKR',
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays(14),
            'current_period_starts_at' => now()->startOfMonth(),
            'current_period_ends_at' => now()->addMonth(),
            'plan_snapshot' => ['limits' => ['orders_per_month' => 20]],
        ]);

        $this->customer = Customer::query()->create([
            'name' => 'Old Contact Name',
            'phone' => '0771234567',
            'email' => 'contact@example.lk',
            'contact_code' => 'CUST-001',
        ]);
    }

    public function test_customer_contact_can_be_partially_updated_without_invalid_rule_name(): void
    {
        $this->patchJson('/api/v1/customers/'.$this->customer->id, [
            'name' => 'Updated Contact Name',
            'contact_code' => 'CUST-001',
        ])
            ->assertOk()
            ->assertJsonPath('name', 'Updated Contact Name')
            ->assertJsonPath('contact_code', 'CUST-001');

        $this->assertDatabaseHas('customers', [
            'id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Updated Contact Name',
        ]);
    }
}
