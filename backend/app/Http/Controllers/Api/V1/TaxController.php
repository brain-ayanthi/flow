<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TaxGroup;
use App\Models\TaxRate;
use App\Services\AuditLogger;
use App\Services\TaxCalculationService;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaxController extends Controller
{
    public function rates(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $rates = TaxRate::query()
            ->withCount('groups')
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = trim((string) $request->query('search'));
                $query->where(fn ($search) => $search->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%"));
            })
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json($rates);
    }

    public function storeRate(Request $request): JsonResponse
    {
        $request->merge(['code' => Str::upper(trim((string) $request->input('code', '')))]);
        $tenantId = app(CurrentTenant::class)->requireId();
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('tax_rates', 'code')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'name' => ['required', 'string', 'max:120'],
            'calculation_type' => ['required', 'in:percentage,fixed'],
            'rate' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'is_compound' => ['sometimes', 'boolean'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $this->assertValidPeriod($data['valid_from'] ?? null, $data['valid_until'] ?? null);
        $data['code'] = Str::upper(trim($data['code']));
        $rate = TaxRate::query()->create($data);
        app(AuditLogger::class)->record('tax_rate.created', $rate, ['code' => $rate->code]);

        return response()->json($rate, 201);
    }

    public function updateRate(Request $request, int $rateId): JsonResponse
    {
        if ($request->exists('code') && $request->input('code') !== null) {
            $request->merge(['code' => Str::upper(trim((string) $request->input('code')))]);
        }
        $rate = TaxRate::query()->findOrFail($rateId);
        $tenantId = app(CurrentTenant::class)->requireId();
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:40', Rule::unique('tax_rates', 'code')->where(fn ($query) => $query->where('tenant_id', $tenantId))->ignore($rate->id)],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'calculation_type' => ['sometimes', 'required', 'in:percentage,fixed'],
            'rate' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999'],
            'is_compound' => ['sometimes', 'boolean'],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_until' => ['sometimes', 'nullable', 'date'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $this->assertValidPeriod(
            array_key_exists('valid_from', $data) ? $data['valid_from'] : $rate->valid_from,
            array_key_exists('valid_until', $data) ? $data['valid_until'] : $rate->valid_until,
        );
        if (isset($data['code'])) {
            $data['code'] = Str::upper(trim($data['code']));
        }
        $rate->fill($data)->save();
        app(AuditLogger::class)->record('tax_rate.updated', $rate, ['fields' => array_keys($data)]);

        return response()->json($rate->fresh()->loadCount('groups'));
    }

    public function archiveRate(int $rateId): JsonResponse
    {
        $rate = TaxRate::query()->findOrFail($rateId);
        $rate->is_active = false;
        $rate->save();
        app(AuditLogger::class)->record('tax_rate.archived', $rate);

        return response()->json(['message' => 'Tax rate deactivated.', 'data' => $rate]);
    }

    public function groups(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $groups = TaxGroup::query()
            ->with('rates')
            ->withCount('rates')
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = trim((string) $request->query('search'));
                $query->where(fn ($search) => $search->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%"));
            })
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json($groups);
    }

    public function showGroup(int $groupId): JsonResponse
    {
        return response()->json(TaxGroup::query()->with('rates')->findOrFail($groupId));
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $request->merge(['code' => Str::upper(trim((string) $request->input('code', '')))]);
        $tenantId = app(CurrentTenant::class)->requireId();
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('tax_groups', 'code')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_inclusive' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'tax_rate_ids' => ['required', 'array', 'min:1', 'max:50'],
            'tax_rate_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $rateIds = $this->requireCurrentTenantRates($data['tax_rate_ids']);
        $data['code'] = Str::upper(trim($data['code']));
        unset($data['tax_rate_ids']);

        $group = DB::transaction(function () use ($data, $rateIds, $tenantId): TaxGroup {
            $group = TaxGroup::query()->create($data);
            $group->rates()->sync($this->orderedPivotData($rateIds, $tenantId));
            app(AuditLogger::class)->record('tax_group.created', $group, ['tax_rate_ids' => $rateIds]);
            return $group->load('rates');
        });

        return response()->json($group, 201);
    }

    public function updateGroup(Request $request, int $groupId): JsonResponse
    {
        if ($request->exists('code') && $request->input('code') !== null) {
            $request->merge(['code' => Str::upper(trim((string) $request->input('code')))]);
        }
        $group = TaxGroup::query()->findOrFail($groupId);
        $tenantId = app(CurrentTenant::class)->requireId();
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:40', Rule::unique('tax_groups', 'code')->where(fn ($query) => $query->where('tenant_id', $tenantId))->ignore($group->id)],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_inclusive' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'tax_rate_ids' => ['sometimes', 'required', 'array', 'min:1', 'max:50'],
            'tax_rate_ids.*' => ['required_with:tax_rate_ids', 'integer', 'distinct'],
        ]);
        $rateIds = array_key_exists('tax_rate_ids', $data)
            ? $this->requireCurrentTenantRates($data['tax_rate_ids'])
            : null;
        unset($data['tax_rate_ids']);
        if (isset($data['code'])) {
            $data['code'] = Str::upper(trim($data['code']));
        }

        $group = DB::transaction(function () use ($group, $data, $rateIds, $tenantId): TaxGroup {
            $group->fill($data)->save();
            if ($rateIds !== null) {
                $group->rates()->sync($this->orderedPivotData($rateIds, $tenantId));
            }
            app(AuditLogger::class)->record('tax_group.updated', $group, ['fields' => array_keys($data)]);
            return $group->fresh()->load('rates');
        });

        return response()->json($group);
    }

    public function archiveGroup(int $groupId): JsonResponse
    {
        $group = TaxGroup::query()->findOrFail($groupId);
        $group->is_active = false;
        $group->save();
        app(AuditLogger::class)->record('tax_group.archived', $group);

        return response()->json(['message' => 'Tax group deactivated.', 'data' => $group]);
    }

    public function previewCalculation(Request $request, TaxCalculationService $taxes): JsonResponse
    {
        $data = $request->validate([
            'tax_group_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'quantity' => ['sometimes', 'numeric', 'gt:0', 'max:999999999'],
        ]);

        return response()->json($taxes->calculate(
            (int) $data['tax_group_id'],
            (float) $data['amount'],
            (float) ($data['quantity'] ?? 1),
        ));
    }

    /** @return array<int> */
    private function requireCurrentTenantRates(array $rateIds): array
    {
        $rateIds = array_values(array_unique(array_map('intval', $rateIds)));
        $count = TaxRate::query()->whereIn('id', $rateIds)->where('is_active', true)->count();
        if ($count !== count($rateIds)) {
            throw ValidationException::withMessages(['tax_rate_ids' => ['Select active tax rates belonging to this business.']]);
        }

        return $rateIds;
    }

    /** @return array<int, array<string, int>> */
    private function orderedPivotData(array $rateIds, int $tenantId): array
    {
        $pivot = [];
        foreach ($rateIds as $position => $rateId) {
            $pivot[$rateId] = ['tenant_id' => $tenantId, 'sort_order' => $position];
        }

        return $pivot;
    }

    private function assertValidPeriod(mixed $validFrom, mixed $validUntil): void
    {
        if ($validFrom !== null && $validUntil !== null
            && Carbon::parse($validUntil)->startOfDay()->lt(Carbon::parse($validFrom)->startOfDay())) {
            throw ValidationException::withMessages(['valid_until' => ['The end date must be on or after the start date.']]);
        }
    }
}
