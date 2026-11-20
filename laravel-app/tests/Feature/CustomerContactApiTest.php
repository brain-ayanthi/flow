<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
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

    public function test_include_walk_in_creates_the_default_contact_for_an_existing_workspace(): void
    {
        $this->assertDatabaseMissing('customers', [
            'tenant_id' => $this->tenant->id,
            'is_default' => true,
        ]);

        $response = $this->getJson('/api/v1/customers?per_page=1&include_walk_in=1')->assertOk();
        $walkIn = Customer::query()->where('is_default', true)->firstOrFail();

        $response->assertJsonPath('data.0.id', $walkIn->id)
            ->assertJsonPath('data.0.name', 'Walk-In Customer')
            ->assertJsonPath('data.0.is_default', true);
        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->tenant->id,
            'id' => $walkIn->id,
            'is_default' => true,
        ]);
    }

    public function test_customer_index_includes_walk_in_only_when_requested_and_orders_it_first(): void
    {
        $walkIn = Customer::query()->create([
            'name' => 'Walk-In Customer',
            'is_customer' => true,
            'is_default' => true,
        ]);

        $this->getJson('/api/v1/customers?per_page=100')
            ->assertOk()
            ->assertJsonMissing(['id' => $walkIn->id]);

        $response = $this->getJson('/api/v1/customers?per_page=100&include_walk_in=1')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $walkIn->id,
                'name' => 'Walk-In Customer',
                'is_default' => true,
            ]);

        $this->assertSame($walkIn->id, (int) $response->json('data.0.id'));
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

    public function test_customer_outstanding_endpoint_lists_unpaid_final_invoices_and_opening_balance(): void
    {
        $this->customer->forceFill([
            'opening_balance' => 125.50,
            'opening_balance_type' => 'debit',
        ])->save();

        Invoice::query()->create([
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-PARTIAL-001',
            'issued_at' => '2026-10-16',
            'total' => 1500,
            'paid_total' => 500,
            'status' => 'partial',
        ]);
        Invoice::query()->create([
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-UNPAID-002',
            'issued_at' => '2026-10-15',
            'total' => 800,
            'paid_total' => 0,
            'status' => 'unpaid',
        ]);
        Invoice::query()->create([
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-PAID-003',
            'issued_at' => '2026-10-14',
            'total' => 250,
            'paid_total' => 250,
            'status' => 'paid',
        ]);
        Invoice::query()->create([
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-DRAFT-004',
            'issued_at' => '2026-10-13',
            'total' => 600,
            'paid_total' => 0,
            'status' => 'unpaid',
            'document_status' => 'draft',
        ]);

        $response = $this->getJson('/api/v1/customers/' . $this->customer->id . '/outstanding')
            ->assertOk()
            ->assertJsonPath('customer_id', $this->customer->id)
            ->assertJsonPath('invoice_count', 2)
            ->assertJsonCount(2, 'invoices');

        $this->assertEquals(1800.0, $response->json('invoice_outstanding_total'));
        $this->assertEquals(125.5, $response->json('opening_amount_due'));
        $this->assertEquals(1925.5, $response->json('outstanding_total'));
        $this->assertSame('INV-PARTIAL-001', $response->json('invoices.0.invoice_number'));
        $this->assertEquals(1000.0, $response->json('invoices.0.balance'));
        $this->assertSame('INV-UNPAID-002', $response->json('invoices.1.invoice_number'));
        $this->assertEquals(800.0, $response->json('invoices.1.balance'));
    }
}
