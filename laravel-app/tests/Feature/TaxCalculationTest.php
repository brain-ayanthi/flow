<?php

namespace Tests\Feature;

use App\Models\TaxGroup;
use App\Models\TaxRate;
use App\Models\Tenant;
use App\Services\TaxCalculationService;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxCalculationTest extends TestCase
{
    use RefreshDatabase;

    private TaxCalculationService $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::query()->create([
            'name' => 'Tax Test Shop',
            'slug' => 'tax-test-shop',
            'default_currency' => 'LKR',
            'default_locale' => 'en',
        ]);
        app(CurrentTenant::class)->set((int) $tenant->id);
        $this->calculator = app(TaxCalculationService::class);
    }

    public function test_exclusive_percentage_tax_is_added_to_the_entered_net_amount(): void
    {
        $group = $this->groupWithRates('STD', 'Standard VAT', false, [
            ['code' => 'VAT18', 'name' => 'VAT 18%', 'calculation_type' => 'percentage', 'rate' => 18, 'is_compound' => false],
        ]);

        $result = $this->calculator->calculate($group->id, 1000, 1);

        $this->assertEquals(1000.00, $result['taxable_amount']);
        $this->assertEquals(180.00, $result['tax_amount']);
        $this->assertEquals(1180.00, $result['total']);
        $this->assertEquals(180.00, $result['rates'][0]['amount']);
    }

    public function test_inclusive_percentage_tax_is_extracted_from_the_entered_gross_amount(): void
    {
        $group = $this->groupWithRates('INCL', 'VAT inclusive', true, [
            ['code' => 'VAT18', 'name' => 'VAT 18%', 'calculation_type' => 'percentage', 'rate' => 18, 'is_compound' => false],
        ]);

        $result = $this->calculator->calculate($group->id, 1180, 1);

        $this->assertEquals(1000.00, $result['taxable_amount']);
        $this->assertEquals(180.00, $result['tax_amount']);
        $this->assertEquals(1180.00, $result['total']);
    }

    private function groupWithRates(string $code, string $name, bool $inclusive, array $definitions): TaxGroup
    {
        $group = TaxGroup::query()->create([
            'code' => $code,
            'name' => $name,
            'is_inclusive' => $inclusive,
            'is_active' => true,
        ]);

        $pivot = [];
        foreach ($definitions as $position => $definition) {
            $rate = TaxRate::query()->create($definition + ['is_active' => true]);
            $pivot[$rate->id] = ['tenant_id' => app(CurrentTenant::class)->requireId(), 'sort_order' => $position];
        }
        $group->rates()->sync($pivot);

        return $group;
    }
}
