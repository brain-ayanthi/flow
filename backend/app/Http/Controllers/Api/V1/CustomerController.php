<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $contacts = Customer::query()
            ->withCount(['orders', 'invoices', 'quotes'])
            ->when(in_array($request->query('type'), ['customer', 'supplier'], true), function ($query) use ($request): void {
                $column = $request->query('type') === 'supplier' ? 'is_supplier' : 'is_customer';
                $query->where($column, true);
            })
            ->when($request->boolean('include_walk_in', false) === false, fn ($query) => $query->where('is_default', false))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = trim((string) $request->query('search'));
                $query->where(function ($search) use ($term): void {
                    $search->where('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('contact_code', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json($contacts);
    }

    public function show(int $customerId): JsonResponse
    {
        $contact = Customer::query()
            ->withCount(['orders', 'invoices', 'quotes'])
            ->findOrFail($customerId);

        return response()->json($contact->load([
            'orders' => fn ($query) => $query->latest()->limit(10),
            'invoices' => fn ($query) => $query->latest('issued_at')->limit(10),
            'quotes' => fn ($query) => $query->latest('issued_at')->limit(10),
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $data['is_customer'] = $data['is_customer'] ?? true;
        $data['is_supplier'] = $data['is_supplier'] ?? false;
        if (! array_key_exists('credit_limit', $data)) {
            $tenant = Tenant::query()->findOrFail(app(CurrentTenant::class)->requireId());
            $defaultCreditLimit = $tenant->settings['contact']['default_credit_limit'] ?? null;
            if ($defaultCreditLimit !== null) {
                $data['credit_limit'] = $defaultCreditLimit;
            }
        }
        $this->assertValidContactTypes($data['is_customer'], $data['is_supplier']);

        $contact = Customer::query()->create($data);
        app(AuditLogger::class)->record('contact.created', $contact, [
            'is_customer' => $contact->is_customer,
            'is_supplier' => $contact->is_supplier,
        ]);

        return response()->json($contact, 201);
    }

    public function update(Request $request, int $customerId): JsonResponse
    {
        $contact = Customer::query()->findOrFail($customerId);
        $data = $request->validate($this->rules(true, $contact->id));
        $this->assertValidContactTypes(
            $data['is_customer'] ?? $contact->is_customer,
            $data['is_supplier'] ?? $contact->is_supplier,
        );

        $contact->fill($data)->save();
        app(AuditLogger::class)->record('contact.updated', $contact, ['fields' => array_keys($data)]);

        return response()->json($contact->fresh());
    }

    private function rules(bool $partial = false, ?int $ignoreId = null): array
    {
        $optional = $partial ? ['sometimes'] : [];
        $contactCodeRule = Rule::unique('customers', 'contact_code')
            ->where(fn ($query) => $query->where('tenant_id', app(CurrentTenant::class)->requireId()));
        if ($ignoreId !== null) {
            $contactCodeRule->ignore($ignoreId);
        }

        return [
            'name' => $partial ? ['sometimes', 'required', 'string', 'max:160'] : ['required', 'string', 'max:160'],
            'phone' => [...$optional, 'nullable', 'string', 'max:30'],
            'email' => [...$optional, 'nullable', 'email', 'max:190'],
            'billing_address' => [...$optional, 'nullable', 'string', 'max:3000'],
            'shipping_address' => [...$optional, 'nullable', 'string', 'max:3000'],
            'notes' => [...$optional, 'nullable', 'string', 'max:5000'],
            'is_customer' => [...$optional, 'boolean'],
            'is_supplier' => [...$optional, 'boolean'],
            'contact_code' => [...$optional, 'nullable', 'string', 'max:40', $contactCodeRule],
            'tax_registration_number' => [...$optional, 'nullable', 'string', 'max:80'],
            'payment_term_days' => [...$optional, 'integer', 'min:0', 'max:3650'],
            'opening_balance' => [...$optional, 'numeric', 'min:0', 'max:999999999999'],
            'opening_balance_type' => [...$optional, 'in:debit,credit'],
            'credit_limit' => [...$optional, 'nullable', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }

    private function assertValidContactTypes(bool $isCustomer, bool $isSupplier): void
    {
        if (! $isCustomer && ! $isSupplier) {
            throw ValidationException::withMessages(['is_customer' => ['A contact must be marked as a customer, supplier, or both.']]);
        }
    }
}
