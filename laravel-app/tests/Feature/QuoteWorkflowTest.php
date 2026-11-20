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

class QuoteWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Test Print Shop',
            'slug' => 'test-print-shop',
            'default_currency' => 'LKR',
            'default_locale' => 'en',
        ]);

        $owner = User::factory()->create();
        $owner->forceFill(['current_tenant_id' => $this->tenant->id])->save();
        $this->tenant->users()->attach($owner->id, ['role' => 'owner']);
        Sanctum::actingAs($owner);
        app(CurrentTenant::class)->set((int) $this->tenant->id);

        $plan = SubscriptionPlan::query()->create([
            'code' => 'test-plan',
            'name' => 'Test plan',
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
            'name' => 'Preview Customer',
            'phone' => '0771234567',
            'email' => 'customer@example.lk',
        ]);
    }

    public function test_accepted_quote_converts_once_and_keeps_server_calculated_totals(): void
    {
        $created = $this->postJson('/api/v1/quotes', [
            'customer_id' => $this->customer->id,
            'title' => 'Shop-front banner',
            'issued_at' => today()->toDateString(),
            'expiry_at' => today()->addDays(7)->toDateString(),
            'items' => [
                [
                    'description' => 'Flex banner 10 × 5 ft',
                    'quantity' => 2,
                    'unit' => 'piece',
                    'unit_price' => 500,
                    'specifications' => ['print_type' => 'flex', 'width_ft' => 10, 'height_ft' => 5],
                ],
                [
                    'description' => 'Eyelet finishing',
                    'quantity' => 5,
                    'unit' => 'ft',
                    'unit_price' => 100,
                    'specifications' => ['print_type' => 'flex'],
                ],
            ],
            'discount' => 100,
            'tax' => 225,
        ])->assertCreated()->assertJsonPath('status', 'draft')->assertJsonPath('subtotal', '1500.00')
            ->assertJsonPath('total', '1625.00');

        $quoteId = (int) $created->json('id');
        $this->patchJson("/api/v1/quotes/{$quoteId}/status", ['status' => 'sent'])->assertOk();
        $this->patchJson("/api/v1/quotes/{$quoteId}/status", ['status' => 'accepted'])->assertOk();

        $converted = $this->postJson("/api/v1/quotes/{$quoteId}/convert")
            ->assertOk()
            ->assertJsonPath('status', 'converted')
            ->assertJsonPath('converted_order.total', '1625.00')
            ->assertJsonCount(1, 'converted_order.invoices')
            ->assertJsonPath('converted_order.invoices.0.total', '1625.00');

        $jobNumber = $converted->json('converted_order.job_number');
        $this->assertNotEmpty($jobNumber);
        $this->assertDatabaseCount('print_orders', 1);
        $this->assertDatabaseCount('print_order_items', 2);

        $this->postJson("/api/v1/quotes/{$quoteId}/convert")
            ->assertOk()
            ->assertJsonPath('converted_order.job_number', $jobNumber);
        $this->assertDatabaseCount('print_orders', 1);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_walk_in_quote_cannot_be_converted_into_an_unpaid_invoice(): void
    {
        $walkIn = Customer::query()->create([
            'name' => 'Walk-In Customer',
            'is_customer' => true,
            'is_default' => true,
        ]);
        $quote = $this->postJson('/api/v1/quotes', [
            'customer_id' => $walkIn->id,
            'title' => 'Walk-in quote',
            'items' => [[
                'description' => 'Printed job',
                'quantity' => 1,
                'unit_price' => 1000,
            ]],
        ])->assertCreated();

        $quoteId = (int) $quote->json('id');
        $this->patchJson("/api/v1/quotes/{$quoteId}/status", ['status' => 'sent'])->assertOk();
        $this->patchJson("/api/v1/quotes/{$quoteId}/status", ['status' => 'accepted'])->assertOk();

        $this->postJson("/api/v1/quotes/{$quoteId}/convert")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_id');

        $this->assertDatabaseCount('print_orders', 0);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseHas('quotes', ['id' => $quoteId, 'status' => 'accepted']);
    }

    public function test_discount_cannot_exceed_the_server_calculated_subtotal(): void
    {
        $this->postJson('/api/v1/quotes', [
            'customer_id' => $this->customer->id,
            'title' => 'Invalid discount quote',
            'items' => [[
                'description' => 'Flyer',
                'quantity' => 10,
                'unit_price' => 100,
            ]],
            'discount' => 1001,
        ])->assertUnprocessable()->assertJsonValidationErrors(['discount']);

        $this->assertDatabaseCount('quotes', 0);
    }
}
