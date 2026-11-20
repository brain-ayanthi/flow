<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderProductionWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Order Workflow Test Shop',
            'slug' => 'order-workflow-test-shop',
            'default_currency' => 'LKR',
            'default_locale' => 'en',
        ]);

        $owner = User::factory()->create();
        $owner->forceFill(['current_tenant_id' => $this->tenant->id])->save();
        $this->tenant->users()->attach($owner->id, ['role' => 'owner']);
        Sanctum::actingAs($owner);
        app(CurrentTenant::class)->set((int) $this->tenant->id);

        $plan = SubscriptionPlan::query()->create([
            'code' => 'order-workflow-test-plan',
            'name' => 'Order workflow test plan',
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
            'name' => 'Workflow Customer',
            'phone' => '0771234567',
            'email' => 'workflow@example.lk',
        ]);
    }

    public function test_jobcard_create_persists_item_snapshot_and_server_calculated_total(): void
    {
        $response = $this->postJson('/api/v1/orders', [
            'customer_id' => $this->customer->id,
            'job_date' => '2026-10-12',
            'contact_name' => 'Front desk contact',
            'contact_phone' => '0777654321',
            'whatsapp_phone' => '0777654321',
            'title' => 'Frontlit banner with eyelets',
            'print_type' => 'flex',
            'due_at' => '2026-10-15 14:30:00',
            'items' => [
                [
                    'description' => 'Frontlit 440gsm · eyelet finishing',
                    'quantity' => 5,
                    'unit' => 'sq ft',
                    'unit_price' => 1850,
                    'specifications' => [
                        'print_type' => 'flex',
                        'media' => 'Frontlit 440gsm',
                        'fabrication' => 'Eyelet',
                        'billing_width_in' => 24,
                        'billing_height_in' => 30,
                        'pieces' => 2,
                        'area_sq_ft' => 5,
                    ],
                ],
                [
                    'description' => 'Transport',
                    'quantity' => 1,
                    'unit' => 'item',
                    'unit_price' => 240,
                ],
            ],
            'discount' => 190,
            'tax' => 0,
            'round_off' => -0.50,
            'notes' => 'Deliver after 2pm',
        ])->assertCreated()
            ->assertJsonPath('subtotal', '9490.00')
            ->assertJsonPath('discount', '190.00')
            ->assertJsonPath('round_off', '-0.50')
            ->assertJsonPath('total', '9299.50')
            ->assertJsonCount(1, 'invoices')
            ->assertJsonPath('invoices.0.total', '9299.50')
            ->assertJsonPath('production_stage', 'new')
            ->assertJsonPath('events.0.to_stage', 'new');

        $orderId = (int) $response->json('id');
        $jobNumber = $response->json('job_number');

        $this->assertNotEmpty($jobNumber);
        $this->assertDatabaseHas('print_orders', [
            'id' => $orderId,
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'contact_name' => 'Front desk contact',
            'whatsapp_phone' => '0777654321',
            'job_number' => $jobNumber,
        ]);

        // SQLite stores a date-cast Carbon value with a midnight time and numeric
        // columns without trailing zeroes; assert the normalized Eloquent casts.
        $order = \App\Models\PrintOrder::query()->findOrFail($orderId);
        $this->assertSame('2026-10-12', $order->job_date->toDateString());
        $this->assertSame('9299.50', $order->total);

        $this->assertDatabaseHas('print_order_items', [
            'tenant_id' => $this->tenant->id,
            'print_order_id' => $orderId,
            'description' => 'Frontlit 440gsm · eyelet finishing',
        ]);
        $item = \App\Models\PrintOrderItem::query()->where('print_order_id', $orderId)->firstOrFail();
        $this->assertSame('9250.00', $item->line_total);
        $this->assertDatabaseCount('print_order_events', 1);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'action' => 'order.created',
            'subject_id' => $orderId,
        ]);
    }

    public function test_flex_jobcard_pricing_converts_feet_and_inches_and_adds_fabrication_charge(): void
    {
        $mediaId = $this->createJobcardMaster(['type' => 'flex_media', 'name' => 'Frontlit 440gsm', 'rate' => 50]);
        $fabricationId = $this->createJobcardMaster([
            'type' => 'fabrication',
            'name' => 'Eyelet',
            'charge_mode' => 'per_piece',
            'rate' => 10,
        ]);

        $basePayload = [
            'customer_id' => $this->customer->id,
            'title' => 'Flex banner',
            'print_type' => 'flex',
        ];
        $feet = $this->postJson('/api/v1/orders', array_merge($basePayload, [
            'items' => [[
                'description' => 'Flex banner',
                'quantity' => 2,
                'unit' => 'piece',
                'unit_price' => 50,
                'specifications' => [
                    'category' => 'flex',
                    'media_id' => $mediaId,
                    'fabrication_id' => $fabricationId,
                    'dimension_unit' => 'ft',
                    'width' => 4,
                    'height' => 3,
                    'billing_width' => 4,
                    'billing_height' => 3,
                    'rate_override' => false,
                ],
            ]],
        ]))->assertCreated()
            ->assertJsonPath('subtotal', '1220.00')
            ->assertJsonPath('total', '1220.00')
            ->assertJsonPath('items.0.quantity', '24.00')
            ->assertJsonPath('items.0.unit', 'sq ft');

        $this->assertNotEmpty($feet->json('job_number'));

        $this->tenant->settings = ['jobcard' => ['default_flex_dimension_unit' => 'in']];
        $this->tenant->save();
        $this->postJson('/api/v1/orders', array_merge($basePayload, [
            'title' => 'Workspace-default inch-based banner',
            'items' => [[
                'description' => 'Flex banner',
                'quantity' => 1,
                'unit' => 'piece',
                'unit_price' => 50,
                'specifications' => [
                    'category' => 'flex',
                    'media_id' => $mediaId,
                    'fabrication_id' => $fabricationId,
                    'width' => 48,
                    'height' => 36,
                    'billing_width' => 48,
                    'billing_height' => 36,
                    'rate_override' => false,
                ],
            ]],
        ]))->assertCreated()
            ->assertJsonPath('subtotal', '610.00')
            ->assertJsonPath('items.0.quantity', '12.00');

        $this->postJson('/api/v1/orders', array_merge($basePayload, [
            'title' => 'Fabrication override banner',
            'items' => [[
                'description' => 'Flex banner',
                'quantity' => 1,
                'unit' => 'piece',
                'unit_price' => 50,
                'specifications' => [
                    'category' => 'flex',
                    'media_id' => $mediaId,
                    'fabrication_id' => $fabricationId,
                    'dimension_unit' => 'in',
                    'width' => 48,
                    'height' => 36,
                    'billing_width' => 48,
                    'billing_height' => 36,
                    'rate_override' => false,
                    'fabrication_rate_override' => true,
                    'fabrication_rate' => 25,
                ],
            ]],
        ]))->assertCreated()
            ->assertJsonPath('subtotal', '625.00')
            ->assertJsonPath('items.0.specifications.fabrication_rate_override', true);

        $this->postJson('/api/v1/orders', array_merge($basePayload, [
            'title' => 'Inch-based flex banner',
            'items' => [[
                'description' => 'Flex banner',
                'quantity' => 1,
                'unit' => 'piece',
                'unit_price' => 50,
                'specifications' => [
                    'category' => 'flex',
                    'media_id' => $mediaId,
                    'fabrication_id' => $fabricationId,
                    'dimension_unit' => 'in',
                    'width' => 48,
                    'height' => 36,
                    'billing_width' => 48,
                    'billing_height' => 36,
                    'rate_override' => false,
                ],
            ]],
        ]))->assertCreated()
            ->assertJsonPath('subtotal', '610.00')
            ->assertJsonPath('total', '610.00')
            ->assertJsonPath('items.0.quantity', '12.00')
            ->assertJsonPath('items.0.unit', 'sq ft');
    }

    public function test_missing_jobcard_settings_default_flex_dimension_unit_to_feet(): void
    {
        $this->tenant->settings = [];
        $this->tenant->save();
        $mediaId = $this->createJobcardMaster(['type' => 'flex_media', 'name' => 'Frontlit Media', 'rate' => 20]);

        $response = $this->postJson('/api/v1/orders', [
            'customer_id' => $this->customer->id,
            'title' => 'Legacy workspace flex job',
            'print_type' => 'flex',
            'items' => [[
                'description' => 'Flex banner',
                'quantity' => 1,
                'unit' => 'piece',
                'unit_price' => 20,
                'specifications' => [
                    'category' => 'flex',
                    'media_id' => $mediaId,
                    'width' => 2,
                    'height' => 3,
                    'rate_override' => false,
                ],
            ]],
        ])->assertCreated()
            ->assertJsonPath('subtotal', '120.00')
            ->assertJsonPath('items.0.quantity', '6.00')
            ->assertJsonPath('items.0.specifications.dimension_unit', 'ft');
    }

    public function test_jobcard_finalization_creates_invoice_and_records_advance_receipt_atomically(): void
    {
        $paymentMethod = PaymentMethod::query()->create([
            'code' => 'cash',
            'name' => 'Cash in Hand',
            'method_type' => 'cash',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->postJson('/api/v1/orders', [
            'customer_id' => $this->customer->id,
            'title' => 'Advance payment jobcard',
            'print_type' => 'digital',
            'auto_invoice' => true,
            'advance_amount' => 500,
            'payment_method' => 'cash',
            'payment_method_id' => $paymentMethod->id,
            'payment_reference' => 'CASH-ADV-001',
            'items' => [[
                'description' => 'Digital print run',
                'quantity' => 100,
                'unit' => 'piece',
                'unit_price' => 35,
            ]],
        ])->assertCreated()
            ->assertJsonPath('total', '3500.00')
            ->assertJsonPath('invoices.0.status', 'partial')
            ->assertJsonPath('invoices.0.total', '3500.00')
            ->assertJsonPath('invoices.0.paid_total', '500.00')
            ->assertJsonPath('invoices.0.payments.0.reference', 'CASH-ADV-001');

        $orderId = (int) $response->json('id');
        $invoiceId = (int) $response->json('invoices.0.id');
        $paymentId = (int) $response->json('invoices.0.payments.0.id');
        $this->assertSame('RCP-' . str_pad((string) $paymentId, 6, '0', STR_PAD_LEFT), $response->json('invoices.0.payments.0.receipt_number'));
        $this->assertDatabaseHas('invoices', [
            'tenant_id' => $this->tenant->id,
            'print_order_id' => $orderId,
            'customer_id' => $this->customer->id,
            'total' => 3500,
            'paid_total' => 500,
            'status' => 'partial',
        ]);
        $this->assertDatabaseHas('payments', [
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoiceId,
            'payment_method_id' => $paymentMethod->id,
            'contact_id' => $this->customer->id,
            'amount' => 500,
            'method' => 'cash',
            'status' => 'succeeded',
            'reference' => 'CASH-ADV-001',
        ]);
    }

    public function test_flex_uses_billing_dimensions_for_price_and_preserves_job_sheet_dimensions(): void
    {
        $mediaId = $this->createJobcardMaster(['type' => 'flex_media', 'name' => 'Frontlit Media', 'rate' => 50]);
        $response = $this->postJson('/api/v1/orders', [
            'customer_id' => $this->customer->id,
            'title' => 'Different production and billing sizes',
            'print_type' => 'flex',
            'items' => [[
                'description' => 'Flex banner',
                'quantity' => 1,
                'unit' => 'piece',
                'unit_price' => 50,
                'specifications' => [
                    'category' => 'flex',
                    'media_id' => $mediaId,
                    'dimension_unit' => 'ft',
                    'width' => 4,
                    'height' => 3,
                    'billing_width' => 5,
                    'billing_height' => 2,
                    'rate_override' => false,
                ],
            ]],
        ])->assertCreated()
            ->assertJsonPath('subtotal', '500.00')
            ->assertJsonPath('items.0.quantity', '10.00');

        $specifications = $response->json('items.0.specifications');
        $this->assertEquals(4.0, (float) $specifications['width']);
        $this->assertEquals(3.0, (float) $specifications['height']);
        $this->assertEquals(5.0, (float) $specifications['billing_width']);
        $this->assertEquals(2.0, (float) $specifications['billing_height']);
    }

    public function test_workspace_settings_reject_primary_and_fabrication_rate_overrides_when_disabled(): void
    {
        $mediaId = $this->createJobcardMaster(['type' => 'flex_media', 'name' => 'Frontlit 440gsm', 'rate' => 50]);
        $fabricationId = $this->createJobcardMaster([
            'type' => 'fabrication',
            'name' => 'Eyelet',
            'charge_mode' => 'per_piece',
            'rate' => 10,
        ]);
        $this->tenant->settings = ['jobcard' => ['allow_rate_override' => false]];
        $this->tenant->save();
        $payload = [
            'customer_id' => $this->customer->id,
            'title' => 'Override permission test',
            'print_type' => 'flex',
            'items' => [[
                'description' => 'Flex banner',
                'quantity' => 1,
                'unit' => 'piece',
                'unit_price' => 99,
                'specifications' => [
                    'category' => 'flex',
                    'media_id' => $mediaId,
                    'fabrication_id' => $fabricationId,
                    'dimension_unit' => 'ft',
                    'width' => 2,
                    'height' => 2,
                    'billing_width' => 2,
                    'billing_height' => 2,
                    'rate_override' => true,
                ],
            ]],
        ];
        $this->postJson('/api/v1/orders', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.unit_price']);

        $payload['items'][0]['unit_price'] = 50;
        $payload['items'][0]['specifications']['rate_override'] = false;
        $payload['items'][0]['specifications']['fabrication_rate_override'] = true;
        $payload['items'][0]['specifications']['fabrication_rate'] = 25;
        $this->postJson('/api/v1/orders', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.specifications.fabrication_rate']);
    }

    public function test_digital_matrix_other_item_default_rate_and_allowed_override_are_used(): void
    {
        $particularId = $this->createJobcardMaster(['type' => 'digital_particular', 'name' => 'Business cards', 'unit' => 'piece']);
        $mediaId = $this->createJobcardMaster(['type' => 'digital_media', 'name' => 'Art card 300gsm']);
        $sizeId = $this->createJobcardMaster(['type' => 'digital_size', 'name' => '3.5 x 2 in']);
        $sideId = $this->createJobcardMaster(['type' => 'digital_side', 'name' => 'Both sides']);
        $otherItemId = $this->createJobcardMaster(['type' => 'other_item', 'name' => 'Delivery trip', 'unit' => 'trip', 'rate' => 250]);

        $this->postJson('/api/v1/jobcard-masters/digital-rates', [
            'particular_id' => $particularId,
            'media_id' => $mediaId,
            'size_id' => $sizeId,
            'side_id' => $sideId,
            'rate' => 20,
        ])->assertCreated();

        $response = $this->postJson('/api/v1/orders', [
            'customer_id' => $this->customer->id,
            'title' => 'Business cards with delivery',
            'print_type' => 'digital',
            'items' => [
                [
                    'description' => 'Business cards',
                    'quantity' => 100,
                    'unit' => 'piece',
                    'unit_price' => 999,
                    'specifications' => [
                        'category' => 'digital',
                        'particular_id' => $particularId,
                        'media_id' => $mediaId,
                        'size_id' => $sizeId,
                        'side_id' => $sideId,
                        'rate_override' => false,
                    ],
                ],
                [
                    'description' => 'Business cards rush rate',
                    'quantity' => 5,
                    'unit' => 'piece',
                    'unit_price' => 30,
                    'specifications' => [
                        'category' => 'digital',
                        'particular_id' => $particularId,
                        'media_id' => $mediaId,
                        'size_id' => $sizeId,
                        'side_id' => $sideId,
                        'rate_override' => true,
                    ],
                ],
                [
                    'description' => 'Delivery',
                    'quantity' => 2,
                    'unit' => 'trip',
                    'unit_price' => 999,
                    'specifications' => [
                        'category' => 'other',
                        'other_item_id' => $otherItemId,
                        'rate_override' => false,
                    ],
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('subtotal', '2650.00')
            ->assertJsonPath('total', '2650.00')
            ->assertJsonPath('items.0.unit_price', '20.00')
            ->assertJsonPath('items.1.unit_price', '30.00')
            ->assertJsonPath('items.2.unit_price', '250.00');

        $this->assertNotEmpty($response->json('job_number'));
    }

    public function test_production_stage_changes_are_validated_and_recorded(): void
    {
        $created = $this->postJson('/api/v1/orders', $this->orderPayload())
            ->assertCreated();
        $orderId = (int) $created->json('id');

        $this->patchJson("/api/v1/orders/{$orderId}/stage", [
            'stage' => 'prepress',
            'note' => 'Artwork check started',
        ])
            ->assertOk()
            ->assertJsonPath('production_stage', 'prepress')
            ->assertJsonPath('status', 'proof');

        $this->patchJson("/api/v1/orders/{$orderId}/stage", [
            'stage' => 'proof_approved',
            'note' => 'Customer proof approved',
        ])
            ->assertOk()
            ->assertJsonPath('production_stage', 'proof_approved')
            ->assertJsonPath('status', 'production');

        $this->patchJson("/api/v1/orders/{$orderId}/stage", ['stage' => 'ready'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stage']);

        $this->assertDatabaseHas('print_orders', [
            'id' => $orderId,
            'production_stage' => 'proof_approved',
            'status' => 'production',
        ]);
        $this->assertDatabaseHas('print_order_events', [
            'print_order_id' => $orderId,
            'from_stage' => 'new',
            'to_stage' => 'prepress',
            'note' => 'Artwork check started',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'action' => 'order.stage_changed',
            'subject_id' => $orderId,
        ]);
        $this->assertDatabaseCount('print_order_events', 3);
    }

    public function test_jobcard_masters_and_pricing_are_scoped_to_the_current_workspace(): void
    {
        $ownMediaId = $this->createJobcardMaster(['type' => 'flex_media', 'name' => 'Own Frontlit', 'rate' => 42]);
        $otherTenant = Tenant::query()->create([
            'name' => 'Private Master Shop',
            'slug' => 'private-master-shop',
            'default_currency' => 'LKR',
            'default_locale' => 'en',
        ]);
        $foreignMediaId = DB::table('jobcard_masters')->insertGetId([
            'tenant_id' => $otherTenant->id,
            'type' => 'flex_media',
            'name' => 'Private Media',
            'unit' => 'sq ft',
            'rate' => 999,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/jobcard-masters')
            ->assertOk()
            ->assertJsonPath('data.masters.flex_media.0.id', $ownMediaId)
            ->assertJsonMissing(['name' => 'Private Media']);

        $this->postJson('/api/v1/orders', [
            'customer_id' => $this->customer->id,
            'title' => 'Cross-tenant master test',
            'print_type' => 'flex',
            'items' => [[
                'description' => 'Flex banner',
                'quantity' => 1,
                'unit' => 'piece',
                'unit_price' => 999,
                'specifications' => [
                    'category' => 'flex',
                    'media_id' => $foreignMediaId,
                    'dimension_unit' => 'ft',
                    'width' => 2,
                    'height' => 2,
                    'billing_width' => 2,
                    'billing_height' => 2,
                    'rate_override' => false,
                ],
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.specifications.media_id']);
    }

    public function test_orders_and_customer_lookups_cannot_cross_workspace_boundaries(): void
    {
        $otherTenant = Tenant::query()->create([
            'name' => 'Another Print Shop',
            'slug' => 'another-print-shop',
            'default_currency' => 'LKR',
            'default_locale' => 'en',
        ]);
        $foreignCustomerId = DB::table('customers')->insertGetId([
            'tenant_id' => $otherTenant->id,
            'name' => 'Private Customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignOrderId = DB::table('print_orders')->insertGetId([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $foreignCustomerId,
            'job_number' => 'FOREIGN-JOB-001',
            'title' => 'Private order',
            'print_type' => 'digital',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/orders', $this->orderPayload(['customer_id' => $foreignCustomerId]))
            ->assertNotFound();
        $this->getJson('/api/v1/orders/'.$foreignOrderId)->assertNotFound();
        $this->getJson('/api/v1/orders?per_page=100')->assertOk()->assertJsonPath('total', 0);
    }

    private function createJobcardMaster(array $payload): int
    {
        return (int) $this->postJson('/api/v1/jobcard-masters', $payload)
            ->assertCreated()
            ->json('data.id');
    }

    private function orderPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'title' => 'Digital business cards',
            'print_type' => 'digital',
            'items' => [[
                'description' => 'Business cards, double-sided',
                'quantity' => 500,
                'unit' => 'piece',
                'unit_price' => 35,
                'specifications' => ['print_type' => 'digital', 'sides' => 'both'],
            ]],
            'discount' => 0,
            'tax' => 0,
            'notes' => 'Test jobcard',
        ], $overrides);
    }
}
