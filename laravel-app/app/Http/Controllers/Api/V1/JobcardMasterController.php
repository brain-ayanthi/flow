<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JobcardDigitalRate;
use App\Models\JobcardMaster;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JobcardMasterController extends Controller
{
    private const MASTER_TYPES = [
        'flex_media', 'fabrication', 'digital_particular', 'digital_media',
        'digital_size', 'digital_side', 'other_item',
    ];

    private const DIGITAL_RATE_TYPES = [
        'particular_id' => 'digital_particular',
        'media_id' => 'digital_media',
        'size_id' => 'digital_size',
        'side_id' => 'digital_side',
    ];

    public function index(): JsonResponse
    {
        $tenant = Tenant::query()->findOrFail(app(CurrentTenant::class)->requireId());
        $masters = JobcardMaster::query()->orderBy('name')->get()->groupBy('type')->map(
            fn ($records) => $records->values()
        );
        $rates = JobcardDigitalRate::query()
            ->with([
                'particular:id,type,name', 'media:id,type,name',
                'size:id,type,name', 'side:id,type,name',
            ])
            ->orderBy('particular_id')->orderBy('media_id')->orderBy('size_id')->orderBy('side_id')
            ->get();
        $jobcardSettings = $tenant->settings['jobcard'] ?? [];
        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'location_id', 'code', 'name', 'method_type']);

        return response()->json(['data' => [
            'masters' => $masters,
            'digital_rates' => $rates,
            'payment_methods' => $paymentMethods,
            'settings' => [
                'default_flex_dimension_unit' => $jobcardSettings['default_flex_dimension_unit'] ?? 'ft',
                'allow_rate_override' => (bool) ($jobcardSettings['allow_rate_override'] ?? true),
            ],
        ]]);
    }

    public function storeMaster(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(self::MASTER_TYPES)],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:48'],
            'unit' => ['nullable', 'string', 'max:24'],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'charge_mode' => ['nullable', Rule::in(['none', 'per_piece', 'per_sq_ft', 'flat_per_line'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->assertUniqueMasterName($data['type'], $data['name']);
        $data = $this->normalizeMasterData($data);
        $master = JobcardMaster::query()->create($data);
        app(AuditLogger::class)->record('jobcard_master.created', $master, [
            'type' => $master->type,
            'name' => $master->name,
        ]);

        return response()->json(['data' => $master], 201);
    }

    public function updateMaster(Request $request, int $masterId): JsonResponse
    {
        $master = JobcardMaster::query()->findOrFail($masterId);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'code' => ['sometimes', 'nullable', 'string', 'max:48'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:24'],
            'rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999'],
            'charge_mode' => ['sometimes', 'nullable', Rule::in(['none', 'per_piece', 'per_sq_ft', 'flat_per_line'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $name = $data['name'] ?? $master->name;
        $this->assertUniqueMasterName($master->type, $name, $master->id);
        $merged = $this->normalizeMasterData(array_merge($master->only([
            'type', 'name', 'code', 'unit', 'rate', 'charge_mode', 'is_active',
        ]), $data));
        DB::transaction(function () use ($master, $merged, $data): void {
            $master->fill($merged)->save();
            if (array_key_exists('is_active', $data) && ! $data['is_active']) {
                $rateForeignKey = match ($master->type) {
                    'digital_particular' => 'particular_id',
                    'digital_media' => 'media_id',
                    'digital_size' => 'size_id',
                    'digital_side' => 'side_id',
                    default => null,
                };
                if ($rateForeignKey !== null) {
                    JobcardDigitalRate::query()->where($rateForeignKey, $master->id)->update([
                        'is_active' => false,
                        'updated_at' => now(),
                    ]);
                }
            }
        });
        app(AuditLogger::class)->record('jobcard_master.updated', $master, [
            'type' => $master->type,
            'fields' => array_keys($data),
        ]);

        return response()->json(['data' => $master->fresh()]);
    }

    public function storeDigitalRate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'particular_id' => ['required', 'integer'],
            'media_id' => ['required', 'integer'],
            'size_id' => ['required', 'integer'],
            'side_id' => ['required', 'integer'],
            'rate' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->validateDigitalMasters($data);
        $this->assertUniqueDigitalCombination($data);
        $rate = JobcardDigitalRate::query()->create($data);
        app(AuditLogger::class)->record('jobcard_digital_rate.created', $rate, [
            'particular_id' => $rate->particular_id,
            'media_id' => $rate->media_id,
            'size_id' => $rate->size_id,
            'side_id' => $rate->side_id,
            'rate' => $rate->rate,
        ]);

        return response()->json(['data' => $rate->load(['particular', 'media', 'size', 'side'])], 201);
    }

    public function updateDigitalRate(Request $request, int $rateId): JsonResponse
    {
        $rate = JobcardDigitalRate::query()->findOrFail($rateId);
        $data = $request->validate([
            'particular_id' => ['sometimes', 'required', 'integer'],
            'media_id' => ['sometimes', 'required', 'integer'],
            'size_id' => ['sometimes', 'required', 'integer'],
            'side_id' => ['sometimes', 'required', 'integer'],
            'rate' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999999999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $merged = array_merge($rate->only(array_keys(self::DIGITAL_RATE_TYPES)), $data);
        $comboChanged = false;
        foreach (array_keys(self::DIGITAL_RATE_TYPES) as $field) {
            if (array_key_exists($field, $data) && (int) $data[$field] !== (int) $rate->{$field}) {
                $comboChanged = true;
                break;
            }
        }
        if ($comboChanged || (bool) ($data['is_active'] ?? $rate->is_active)) {
            $this->validateDigitalMasters($merged);
        }
        $this->assertUniqueDigitalCombination($merged, $rate->id);
        $rate->fill($data)->save();
        app(AuditLogger::class)->record('jobcard_digital_rate.updated', $rate, [
            'fields' => array_keys($data),
        ]);

        return response()->json(['data' => $rate->fresh()->load(['particular', 'media', 'size', 'side'])]);
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function normalizeMasterData(array $data): array
    {
        $type = $data['type'];
        $name = trim((string) $data['name']);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => ['Enter a master name.']]);
        }

        if ($type === 'flex_media') {
            if (! isset($data['rate']) || ! is_numeric($data['rate'])) {
                throw ValidationException::withMessages(['rate' => ['Enter the media rate per square foot.']]);
            }
            $data['unit'] = 'sq ft';
            $data['charge_mode'] = null;
        } elseif ($type === 'fabrication') {
            $mode = $data['charge_mode'] ?? null;
            if (! in_array($mode, ['none', 'per_piece', 'per_sq_ft', 'flat_per_line'], true)) {
                throw ValidationException::withMessages(['charge_mode' => ['Choose a fabrication charge mode.']]);
            }
            if ($mode !== 'none' && (! isset($data['rate']) || ! is_numeric($data['rate']))) {
                throw ValidationException::withMessages(['rate' => ['Enter the fabrication charge for the selected charge mode.']]);
            }
            $data['rate'] = $mode === 'none' ? 0 : (float) $data['rate'];
            $data['unit'] = null;
        } elseif ($type === 'other_item') {
            if (! isset($data['rate']) || ! is_numeric($data['rate'])) {
                throw ValidationException::withMessages(['rate' => ['Enter the default unit rate.']]);
            }
            $data['unit'] = trim((string) ($data['unit'] ?? 'item')) ?: 'item';
            $data['charge_mode'] = null;
        } else {
            $data['unit'] = $type === 'digital_particular'
                ? (trim((string) ($data['unit'] ?? 'piece')) ?: 'piece')
                : null;
            $data['rate'] = null;
            $data['charge_mode'] = null;
        }

        $data['name'] = $name;
        $data['code'] = isset($data['code']) && trim((string) $data['code']) !== ''
            ? Str::upper(trim((string) $data['code']))
            : null;
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }

    private function assertUniqueMasterName(string $type, string $name, ?int $ignoreId = null): void
    {
        $query = JobcardMaster::query()
            ->where('type', $type)
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim($name))]);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['name' => ['A master with this name already exists in this category.']]);
        }
    }

    /** @param array<string, mixed> $data */
    private function validateDigitalMasters(array $data): void
    {
        foreach (self::DIGITAL_RATE_TYPES as $field => $type) {
            $id = $data[$field] ?? null;
            $master = is_numeric($id)
                ? JobcardMaster::query()->where('type', $type)->where('is_active', true)->find((int) $id)
                : null;
            if (! $master) {
                throw ValidationException::withMessages([$field => ['Choose an active master from the matching category.']]);
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function assertUniqueDigitalCombination(array $data, ?int $ignoreId = null): void
    {
        $query = JobcardDigitalRate::query()
            ->where('particular_id', $data['particular_id'])
            ->where('media_id', $data['media_id'])
            ->where('size_id', $data['size_id'])
            ->where('side_id', $data['side_id']);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['rate' => ['A price already exists for this particular, media, size and side combination. Edit that price instead.']]);
        }
    }
}
