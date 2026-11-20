<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\BusinessSettingsService;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private BusinessSettingsService $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->create([
            'name' => 'Settings Test Shop',
            'slug' => 'settings-test-shop',
            'default_currency' => 'LKR',
            'default_locale' => 'en',
        ]);
        app(CurrentTenant::class)->set((int) $this->tenant->id);
        $this->settings = app(BusinessSettingsService::class);
    }

    public function test_business_preferences_update_typed_tenant_columns_and_settings_json(): void
    {
        $updated = $this->settings->update($this->tenant, 'business', [
            'name' => 'Updated Print Shop',
            'start_date' => '2026-01-05',
            'currency' => 'LKR',
            'currency_symbol_placement' => 'after',
            'timezone' => 'Asia/Colombo',
            'financial_year_start_month' => 4,
            'default_profit_margin' => 25,
            'transaction_edit_days' => 14,
            'date_format' => 'd/m/Y',
            'time_format' => '24h',
            'currency_precision' => 2,
            'quantity_precision' => 3,
            'locale' => 'si',
        ]);

        $fresh = $this->tenant->fresh();
        $this->assertSame('Updated Print Shop', $fresh->name);
        $this->assertSame('LKR', $fresh->default_currency);
        $this->assertSame('si', $fresh->default_locale);
        $this->assertSame('2026-01-05', $fresh->business_start_date->format('Y-m-d'));
        $this->assertSame('after', $updated['business']['currency_symbol_placement']);
        $this->assertSame(14, $updated['business']['transaction_edit_days']);
    }

    public function test_module_and_reward_settings_are_merged_without_losing_other_defaults(): void
    {
        $updated = $this->settings->update($this->tenant, 'modules', [
            'enabled_modules' => ['pos' => false, 'bookings' => true],
        ]);
        $updated = $this->settings->update($this->tenant, 'rewards', [
            'enabled' => true,
            'display_name' => 'Print Points',
            'spend_per_point' => 500,
            'expiry_value' => 12,
            'expiry_unit' => 'months',
        ]);

        $this->assertFalse($updated['modules']['enabled_modules']['pos']);
        $this->assertTrue($updated['modules']['enabled_modules']['bookings']);
        $this->assertTrue($updated['modules']['enabled_modules']['purchases']);
        $this->assertTrue($updated['rewards']['enabled']);
        $this->assertSame('Print Points', $updated['rewards']['display_name']);
        $this->assertSame(12, $updated['rewards']['expiry_value']);
    }

    public function test_prefix_settings_are_also_written_to_business_number_sequences(): void
    {
        $updated = $this->settings->update($this->tenant, 'prefixes', [
            'purchase' => 'PUR',
            'stock_transfer' => 'MOVE',
        ]);

        $this->assertDatabaseHas('number_sequences', [
            'tenant_id' => $this->tenant->id,
            'location_id' => null,
            'sequence_type' => 'purchase',
            'prefix' => 'PUR',
        ]);
        $this->assertDatabaseHas('number_sequences', [
            'tenant_id' => $this->tenant->id,
            'location_id' => null,
            'sequence_type' => 'stock_transfer',
            'prefix' => 'MOVE',
        ]);
        $this->assertSame('PUR', $updated['prefixes']['purchase']);
        $this->assertSame('MOVE', $updated['prefixes']['stock_transfer']);
    }
}
