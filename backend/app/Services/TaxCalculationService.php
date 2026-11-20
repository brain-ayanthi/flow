<?php

namespace App\Services;

use App\Models\TaxGroup;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TaxCalculationService
{
    /**
     * Calculate taxes for one discounted line amount. In an inclusive group, $amount
     * is the tax-included gross; otherwise it is the net amount before tax.
     * Fixed rates are applied per unit and percentage rates are applied in group order.
     *
     * @return array<string, mixed>
     */
    public function calculate(int $taxGroupId, float $amount, float $quantity = 1): array
    {
        if ($amount < 0 || $quantity <= 0) {
            throw ValidationException::withMessages(['amount' => ['Amount must be zero or more and quantity must be greater than zero.']]);
        }

        $group = TaxGroup::query()->with('rates')->findOrFail($taxGroupId);

        if (! $group->is_active) {
            throw ValidationException::withMessages(['tax_group_id' => ['This tax group is inactive.']]);
        }

        $today = today();
        $invalidRate = $group->rates->first(fn ($rate) => ! $rate->is_active
            || ($rate->valid_from && $rate->valid_from->gt($today))
            || ($rate->valid_until && $rate->valid_until->lt($today)));
        if ($invalidRate) {
            throw ValidationException::withMessages(['tax_group_id' => ['This tax group contains an inactive or out-of-date rate. Update the group before using it.']]);
        }
        if ($group->rates->isEmpty()) {
            throw ValidationException::withMessages(['tax_group_id' => ['This tax group has no tax rates.']]);
        }

        $amount = round($amount, 2);
        $rates = $group->rates;
        if (! $group->is_inclusive) {
            $taxable = $amount;
            $taxParts = $this->exclusiveTaxParts($taxable, $quantity, $rates);
            $taxTotal = round(array_sum(array_column($taxParts, 'amount')), 2);
            $taxParts = $this->roundPartsToTotal($taxParts, $taxTotal);
            $gross = round($taxable + $taxTotal, 2);
        } else {
            // Solve gross = net + tax(net), covering compound and fixed-rate groups.
            $low = 0.0;
            $high = $amount;
            for ($i = 0; $i < 64; $i++) {
                $middle = ($low + $high) / 2;
                $parts = $this->exclusiveTaxParts($middle, $quantity, $rates);
                $grossAtMiddle = $middle + array_sum(array_column($parts, 'amount'));
                if ($grossAtMiddle > $amount) {
                    $high = $middle;
                } else {
                    $low = $middle;
                }
            }

            $taxable = round(($low + $high) / 2, 2);
            $taxTotal = round($amount - $taxable, 2);
            $taxParts = $this->exclusiveTaxParts($taxable, $quantity, $rates);
            $taxParts = $this->roundPartsToTotal($taxParts, $taxTotal);
            $gross = $amount;
        }

        return [
            'tax_group_id' => $group->id,
            'tax_group' => $group->name,
            'is_inclusive' => $group->is_inclusive,
            'quantity' => round($quantity, 4),
            'taxable_amount' => round($taxable, 2),
            'tax_amount' => round($taxTotal, 2),
            'total' => round($gross, 2),
            'rates' => $taxParts,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function exclusiveTaxParts(float $base, float $quantity, Collection $rates): array
    {
        $runningBase = $base;
        $parts = [];

        foreach ($rates as $rate) {
            $rateValue = (float) $rate->rate;
            if ($rate->calculation_type === 'fixed') {
                $tax = $rateValue * $quantity;
            } else {
                $taxBase = $rate->is_compound ? $runningBase : $base;
                $tax = $taxBase * ($rateValue / 100);
            }

            $tax = round($tax, 8);
            $parts[] = [
                'tax_rate_id' => $rate->id,
                'code' => $rate->code,
                'name' => $rate->name,
                'calculation_type' => $rate->calculation_type,
                'rate' => (float) $rate->rate,
                'is_compound' => $rate->is_compound,
                'amount' => $tax,
            ];

            if ($rate->is_compound) {
                $runningBase += $tax;
            }
        }

        return $parts;
    }

    /** @param array<int, array<string, mixed>> $parts
     *  @return array<int, array<string, mixed>>
     */
    private function roundPartsToTotal(array $parts, float $target): array
    {
        $rounded = array_map(function (array $part): array {
            $part['amount'] = round((float) $part['amount'], 2);
            return $part;
        }, $parts);

        if ($rounded === []) {
            return $rounded;
        }

        $difference = round($target - array_sum(array_column($rounded, 'amount')), 2);
        $last = array_key_last($rounded);
        $rounded[$last]['amount'] = round($rounded[$last]['amount'] + $difference, 2);

        return $rounded;
    }
}
