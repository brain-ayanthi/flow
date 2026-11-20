<?php

namespace App\Services;

use App\Models\JobcardDigitalRate;
use App\Models\JobcardMaster;
use Illuminate\Validation\ValidationException;

class JobcardPricingService
{
    /**
     * Return a server-priced item for the new jobcard wizard, or null for a
     * legacy/manual API item which continues to use quantity × unit_price.
     *
     * @param array<string, mixed> $line
     * @return array<string, mixed>|null
     */
    public function priceLine(array $line, int $index, bool $allowRateOverride, string $defaultFlexDimensionUnit = 'ft'): ?array
    {
        $specifications = $line['specifications'] ?? [];
        $category = $specifications['category'] ?? null;
        if (! in_array($category, ['flex', 'digital', 'other'], true)) {
            return null;
        }

        return match ($category) {
            'flex' => $this->priceFlex($line, $specifications, $index, $allowRateOverride, $defaultFlexDimensionUnit),
            'digital' => $this->priceDigital($line, $specifications, $index, $allowRateOverride),
            'other' => $this->priceOther($line, $specifications, $index, $allowRateOverride),
        };
    }

    /** @param array<string, mixed> $line
     *  @param array<string, mixed> $specifications
     *  @return array<string, mixed>
     */
    private function priceFlex(array $line, array $specifications, int $index, bool $allowRateOverride, string $defaultFlexDimensionUnit): array
    {
        $media = $this->master($specifications['media_id'] ?? null, 'flex_media', "items.{$index}.specifications.media_id");
        $fabrication = null;
        if (! empty($specifications['fabrication_id'])) {
            $fabrication = $this->master($specifications['fabrication_id'], 'fabrication', "items.{$index}.specifications.fabrication_id");
        }

        $dimensionUnit = $specifications['dimension_unit'] ?? $defaultFlexDimensionUnit;
        if (! in_array($dimensionUnit, ['ft', 'in'], true)) {
            $this->invalid("items.{$index}.specifications.dimension_unit", 'Choose feet or inches for flex dimensions.');
        }

        $pieces = $this->positiveNumber($line['quantity'] ?? null, "items.{$index}.quantity", 'Enter a positive piece quantity.');
        $width = $this->positiveNumber($specifications['width'] ?? null, "items.{$index}.specifications.width", 'Enter the physical width.');
        $height = $this->positiveNumber($specifications['height'] ?? null, "items.{$index}.specifications.height", 'Enter the physical height.');
        $billingWidth = $this->positiveNumber($specifications['billing_width'] ?? $width, "items.{$index}.specifications.billing_width", 'Enter the billing width.');
        $billingHeight = $this->positiveNumber($specifications['billing_height'] ?? $height, "items.{$index}.specifications.billing_height", 'Enter the billing height.');
        $conversion = $dimensionUnit === 'in' ? 1 / 144 : 1;
        $areaPerPiece = ($billingWidth * $billingHeight) * $conversion;
        $billableArea = round($areaPerPiece * $pieces, 2);
        if ($billableArea <= 0) {
            $this->invalid("items.{$index}.specifications.billing_width", 'The billable area must be greater than zero.');
        }

        $mediaRate = $this->rate($line, $specifications, $media, $index, $allowRateOverride);
        $mediaAmount = round($billableArea * $mediaRate, 2);
        $fabricationCharge = 0.0;
        $fabricationMode = $fabrication?->charge_mode ?: 'none';
        $fabricationRateOverride = $fabrication !== null && (bool) ($specifications['fabrication_rate_override'] ?? false);
        $fabricationRate = $fabrication
            ? $this->fabricationRate($specifications, $fabrication, $index, $allowRateOverride)
            : 0.0;
        if ($fabrication) {
            $fabricationCharge = match ($fabricationMode) {
                'none' => 0.0,
                'per_piece' => round($fabricationRate * $pieces, 2),
                'per_sq_ft' => round($fabricationRate * $billableArea, 2),
                'flat_per_line' => round($fabricationRate, 2),
                default => $this->invalid("items.{$index}.specifications.fabrication_id", 'The selected fabrication has an invalid charge mode.'),
            };
        }

        $lineTotal = round($mediaAmount + $fabricationCharge, 2);
        $fabricationName = $fabrication?->name;
        $description = trim($media->name . ($fabricationName ? ' · ' . $fabricationName : ''));

        return [
            'description' => mb_substr($description, 0, 180),
            'quantity' => $billableArea,
            'unit' => 'sq ft',
            'unit_price' => $mediaRate,
            'line_total' => $lineTotal,
            'specifications' => [
                'category' => 'flex',
                'media_id' => $media->id,
                'media_name' => $media->name,
                'fabrication_id' => $fabrication?->id,
                'fabrication_name' => $fabricationName,
                'fabrication_charge_mode' => $fabricationMode,
                'fabrication_rate' => round($fabricationRate, 2),
                'fabrication_rate_override' => $fabricationRateOverride,
                'fabrication_charge' => $fabricationCharge,
                'dimension_unit' => $dimensionUnit,
                'width' => round($width, 2),
                'height' => round($height, 2),
                'billing_width' => round($billingWidth, 2),
                'billing_height' => round($billingHeight, 2),
                'pieces' => round($pieces, 2),
                'area_sq_ft' => $billableArea,
                'media_rate_per_sq_ft' => round($mediaRate, 2),
                'media_amount' => $mediaAmount,
                'rate_override' => (bool) ($specifications['rate_override'] ?? false),
                'remark' => $specifications['remark'] ?? null,
            ],
        ];
    }

    /** @param array<string, mixed> $line
     *  @param array<string, mixed> $specifications
     *  @return array<string, mixed>
     */
    private function priceDigital(array $line, array $specifications, int $index, bool $allowRateOverride): array
    {
        $particular = $this->master($specifications['particular_id'] ?? null, 'digital_particular', "items.{$index}.specifications.particular_id");
        $media = $this->master($specifications['media_id'] ?? null, 'digital_media', "items.{$index}.specifications.media_id");
        $size = $this->master($specifications['size_id'] ?? null, 'digital_size', "items.{$index}.specifications.size_id");
        $side = $this->master($specifications['side_id'] ?? null, 'digital_side', "items.{$index}.specifications.side_id");
        $rateRecord = JobcardDigitalRate::query()
            ->where('particular_id', $particular->id)
            ->where('media_id', $media->id)
            ->where('size_id', $size->id)
            ->where('side_id', $side->id)
            ->where('is_active', true)
            ->first();
        $baseRate = $rateRecord ? (float) $rateRecord->rate : null;
        if ($baseRate === null && ! (bool) ($specifications['rate_override'] ?? false)) {
            $this->invalid("items.{$index}.unit_price", 'No active digital price exists for this particular, media, size and side combination. Add the price in Jobcard Masters or enter an allowed override.');
        }
        $unitRate = $this->rate($line, $specifications, $baseRate, $index, $allowRateOverride);
        $quantity = $this->positiveNumber($line['quantity'] ?? null, "items.{$index}.quantity", 'Enter a positive quantity.');
        $lineTotal = round($quantity * $unitRate, 2);
        $unit = $particular->unit ?: 'piece';
        $description = implode(' · ', [$particular->name, $media->name, $size->name, $side->name]);

        return [
            'description' => mb_substr($description, 0, 180),
            'quantity' => round($quantity, 2),
            'unit' => $unit,
            'unit_price' => round($unitRate, 2),
            'line_total' => $lineTotal,
            'specifications' => [
                'category' => 'digital',
                'particular_id' => $particular->id,
                'particular_name' => $particular->name,
                'media_id' => $media->id,
                'media_name' => $media->name,
                'size_id' => $size->id,
                'size_name' => $size->name,
                'side_id' => $side->id,
                'side_name' => $side->name,
                'rate_matrix_id' => $rateRecord?->id,
                'rate_override' => (bool) ($specifications['rate_override'] ?? false),
                'remark' => $specifications['remark'] ?? null,
            ],
        ];
    }

    /** @param array<string, mixed> $line
     *  @param array<string, mixed> $specifications
     *  @return array<string, mixed>
     */
    private function priceOther(array $line, array $specifications, int $index, bool $allowRateOverride): array
    {
        $itemMaster = $this->master($specifications['other_item_id'] ?? null, 'other_item', "items.{$index}.specifications.other_item_id");
        $unitRate = $this->rate($line, $specifications, $itemMaster, $index, $allowRateOverride);
        $quantity = $this->positiveNumber($line['quantity'] ?? null, "items.{$index}.quantity", 'Enter a positive quantity.');
        $lineTotal = round($quantity * $unitRate, 2);
        $size = trim((string) ($specifications['size'] ?? ''));
        $description = $itemMaster->name . ($size !== '' ? ' · ' . $size : '');

        return [
            'description' => mb_substr($description, 0, 180),
            'quantity' => round($quantity, 2),
            'unit' => $itemMaster->unit ?: 'item',
            'unit_price' => round($unitRate, 2),
            'line_total' => $lineTotal,
            'specifications' => [
                'category' => 'other',
                'other_item_id' => $itemMaster->id,
                'other_item_name' => $itemMaster->name,
                'size' => $size !== '' ? mb_substr($size, 0, 120) : null,
                'rate_override' => (bool) ($specifications['rate_override'] ?? false),
                'remark' => $specifications['remark'] ?? null,
            ],
        ];
    }

    /** @param array<string, mixed> $specifications */
    private function fabricationRate(array $specifications, JobcardMaster $master, int $index, bool $allowRateOverride): float
    {
        $override = (bool) ($specifications['fabrication_rate_override'] ?? false);
        if ($override && ! $allowRateOverride) {
            $this->invalid("items.{$index}.specifications.fabrication_rate", 'Fabrication rate overrides are disabled in Jobcard Settings.');
        }
        $value = $override ? ($specifications['fabrication_rate'] ?? null) : $master->rate;
        if (! is_numeric($value) || (float) $value < 0) {
            $this->invalid("items.{$index}.specifications.fabrication_rate", 'Enter a valid non-negative fabrication charge.');
        }

        return round((float) $value, 2);
    }

    private function master(mixed $id, string $type, string $field): JobcardMaster
    {
        $master = is_numeric($id)
            ? JobcardMaster::query()->where('type', $type)->where('is_active', true)->find((int) $id)
            : null;
        if (! $master) {
            $this->invalid($field, 'Select an active value from the matching Jobcard Master.');
        }

        return $master;
    }

    /** @param array<string, mixed> $line
     *  @param array<string, mixed>|JobcardMaster|float|null $rateSource
     */
    private function rate(array $line, array $specifications, array|JobcardMaster|float|null $rateSource, int $index, bool $allowRateOverride): float
    {
        $override = (bool) ($specifications['rate_override'] ?? false);
        if ($override && ! $allowRateOverride) {
            $this->invalid("items.{$index}.unit_price", 'Rate overrides are disabled in Jobcard Settings.');
        }

        if ($override) {
            $value = $line['unit_price'] ?? null;
            if (! is_numeric($value) || (float) $value < 0) {
                $this->invalid("items.{$index}.unit_price", 'Enter a valid non-negative override rate.');
            }

            return round((float) $value, 2);
        }

        $value = match (true) {
            $rateSource instanceof JobcardMaster => $rateSource->rate,
            is_array($rateSource) && isset($rateSource['rate']) => $rateSource['rate'],
            is_numeric($rateSource) => $rateSource,
            default => null,
        };
        if (! is_numeric($value) || (float) $value < 0) {
            $this->invalid("items.{$index}.unit_price", 'The selected master does not have a valid rate.');
        }

        return round((float) $value, 2);
    }

    private function positiveNumber(mixed $value, string $field, string $message): float
    {
        if (! is_numeric($value) || (float) $value <= 0) {
            $this->invalid($field, $message);
        }

        return (float) $value;
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
