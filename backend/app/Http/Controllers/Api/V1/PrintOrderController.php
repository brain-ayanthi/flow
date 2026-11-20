<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PrintOrder;
use App\Models\PrintOrderEvent;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Services\InvoiceService;
use App\Services\JobcardPricingService;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PrintOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $allowedStatuses = ['new', 'proof', 'production', 'finishing', 'ready', 'delivered', 'cancelled'];

        $orders = PrintOrder::query()
            ->with('customer:id,name,phone')
            ->when(in_array($request->query('status'), $allowedStatuses, true),
                fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = trim((string) $request->query('search'));
                $query->where(function ($search) use ($term): void {
                    $search->where('job_number', 'like', "%{$term}%")
                        ->orWhere('title', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$term}%"));
                });
            })
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($orders);
    }

    public function show(int $orderId): JsonResponse
    {
        $order = PrintOrder::query()->findOrFail($orderId);

        return response()->json($order->load([
            'customer:id,name,phone,email',
            'items',
            'events.changedBy:id,name',
            'invoices:id,print_order_id,invoice_number,total,paid_total,status',
            'invoices.payments',
        ]));
    }

    public function store(Request $request, JobcardPricingService $pricing, InvoiceService $invoiceService): JsonResponse
    {
        $tenantId = app(CurrentTenant::class)->requireId();
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'job_date' => ['nullable', 'date'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'whatsapp_phone' => ['nullable', 'string', 'max:30'],
            'title' => ['required', 'string', 'max:180'],
            'print_type' => ['required', 'in:digital,flex,offset,signage,other'],
            'description' => ['nullable', 'string', 'max:5000'],
            'specifications' => ['nullable', 'array'],
            'quantity' => ['nullable', 'numeric', 'min:0.01', 'max:999999999'],
            'unit' => ['nullable', 'string', 'max:24'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'], 
            'items' => ['nullable', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required_with:items', 'string', 'max:180'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.01', 'max:999999999'],
            'items.*.unit' => ['nullable', 'string', 'max:24'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'items.*.specifications' => ['nullable', 'array'],
            'items.*.specifications.category' => ['nullable', 'in:flex,digital,other'],
            'items.*.specifications.rate_override' => ['nullable', 'boolean'],
            'items.*.specifications.fabrication_rate_override' => ['nullable', 'boolean'],
            'items.*.specifications.fabrication_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'items.*.specifications.media_id' => ['nullable', 'integer'],
            'items.*.specifications.fabrication_id' => ['nullable', 'integer'],
            'items.*.specifications.particular_id' => ['nullable', 'integer'],
            'items.*.specifications.size_id' => ['nullable', 'integer'],
            'items.*.specifications.side_id' => ['nullable', 'integer'],
            'items.*.specifications.other_item_id' => ['nullable', 'integer'],
            'items.*.specifications.dimension_unit' => ['nullable', 'in:ft,in'],
            'items.*.specifications.width' => ['nullable', 'numeric', 'gt:0', 'max:999999'],
            'items.*.specifications.height' => ['nullable', 'numeric', 'gt:0', 'max:999999'],
            'items.*.specifications.billing_width' => ['nullable', 'numeric', 'gt:0', 'max:999999'],
            'items.*.specifications.billing_height' => ['nullable', 'numeric', 'gt:0', 'max:999999'],
            'items.*.specifications.remark' => ['nullable', 'string', 'max:120'],
            'items.*.specifications.size' => ['nullable', 'string', 'max:120'],
            'items.*.specifications.print_type' => ['nullable', 'string', 'max:40'],
            'items.*.specifications.sides' => ['nullable', 'string', 'max:40'],
            'items.*.specifications.media' => ['nullable', 'string', 'max:120'],
            'items.*.specifications.fabrication' => ['nullable', 'string', 'max:120'],
            'items.*.specifications.particular' => ['nullable', 'string', 'max:120'],
            'items.*.specifications.side' => ['nullable', 'string', 'max:40'],
            'items.*.specifications.item_name' => ['nullable', 'string', 'max:120'],
            'items.*.specifications.unit' => ['nullable', 'string', 'max:24'],
            'items.*.specifications.area_sq_ft' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'items.*.specifications.pieces' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.specifications.rate' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],

            'subtotal' => ['required_without:items', 'numeric', 'min:0', 'max:999999999999'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'tax' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'round_off' => ['nullable', 'numeric', 'min:-999999999999', 'max:999999999999'],
            'due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'auto_invoice' => ['sometimes', 'boolean'],
            'advance_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'payment_method' => ['nullable', Rule::in(['cash', 'bank_transfer', 'card_manual', 'cheque'])],
            'payment_method_id' => [
                'nullable',
                'integer',
                Rule::exists('payment_methods', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true)),
            ],
            'payment_reference' => ['nullable', 'string', 'max:120'],
        ]);

        // Tenant-scoped lookup: another shop's customer ID cannot be attached to this job.
        $customer = Customer::query()->findOrFail($data['customer_id']);
        $tenant = Tenant::query()->findOrFail($tenantId);
        $jobcardSettings = $tenant->settings['jobcard'] ?? [];
        $allowRateOverride = (bool) ($jobcardSettings['allow_rate_override'] ?? true);
        $defaultFlexDimensionUnit = $jobcardSettings['default_flex_dimension_unit'] ?? null;
        if (! in_array($defaultFlexDimensionUnit, ['ft', 'in'], true)) {
            $defaultFlexDimensionUnit = 'ft';
        }
        $lines = [];
        $calculatedSubtotal = 0.0;

        foreach ($data['items'] ?? [] as $index => $item) {
            $pricedLine = $pricing->priceLine($item, (int) $index, $allowRateOverride, $defaultFlexDimensionUnit);
            if ($pricedLine !== null) {
                $lines[] = $pricedLine;
                $calculatedSubtotal += (float) $pricedLine['line_total'];
                continue;
            }

            if (! isset($item['unit_price']) || ! is_numeric($item['unit_price'])) {
                throw ValidationException::withMessages([
                    "items.{$index}.unit_price" => ['Enter a unit rate or select a priced Jobcard Master.'],
                ]);
            }

            $quantity = round((float) $item['quantity'], 2);
            $unitPrice = round((float) $item['unit_price'], 2);
            $lineTotal = round($quantity * $unitPrice, 2);
            $calculatedSubtotal += $lineTotal;
            $lines[] = [
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? 'item',
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'specifications' => $item['specifications'] ?? null,
            ];
        }

        $subtotal = round($lines ? $calculatedSubtotal : (float) $data['subtotal'], 2);
        $discount = round((float) ($data['discount'] ?? 0), 2);
        $tax = round((float) ($data['tax'] ?? 0), 2);
        $roundOff = round((float) ($data['round_off'] ?? 0), 2);
        $total = round($subtotal - $discount + $tax + $roundOff, 2);

        if ($discount > $subtotal) {
            throw ValidationException::withMessages(['discount' => ['Discount cannot exceed the subtotal.']]);
        }
        if ($total < 0) {
            throw ValidationException::withMessages(['round_off' => ['The final job total cannot be negative.']]);
        }

        $autoInvoice = (bool) ($data['auto_invoice'] ?? true);
        if (! $autoInvoice) {
            throw ValidationException::withMessages(['auto_invoice' => ['Invoices are generated automatically for new jobcards.']]);
        }
        $advanceAmount = round((float) ($data['advance_amount'] ?? 0), 2);
        if ($advanceAmount > 0) {
            $paymentErrors = [];
            if (! $autoInvoice) {
                $paymentErrors['auto_invoice'] = ['An invoice is required before an advance can be recorded.'];
            }
            if (empty($data['payment_method'])) {
                $paymentErrors['payment_method'] = ['Choose a payment mode to record the advance.'];
            }
            if (empty($data['payment_method_id'])) {
                $paymentErrors['payment_method_id'] = ['Choose a deposit account to record the advance.'];
            }
            if ($advanceAmount > $total) {
                $paymentErrors['advance_amount'] = ['Advance cannot exceed the final bill.'];
            }
            if ($paymentErrors) {
                throw ValidationException::withMessages($paymentErrors);
            }
        }

        $order = DB::transaction(function () use ($data, $customer, $lines, $subtotal, $discount, $tax, $roundOff, $total, $request, $invoiceService, $autoInvoice, $advanceAmount): PrintOrder {
            $order = PrintOrder::query()->create([
                'customer_id' => $customer->id,
                'job_date' => $data['job_date'] ?? today(),
                'contact_name' => $data['contact_name'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'whatsapp_phone' => $data['whatsapp_phone'] ?? null,
                'job_number' => 'PF-' . Str::upper(Str::random(10)),
                'title' => $data['title'],
                'print_type' => $data['print_type'],
                'description' => $data['description'] ?? null,
                'specifications' => $data['specifications'] ?? null,
                'quantity' => $data['quantity'] ?? ($lines ? array_sum(array_column($lines, 'quantity')) : 1),
                'unit' => $data['unit'] ?? 'item',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'round_off' => $roundOff,
                'total' => $total,
                'due_at' => $data['due_at'] ?? null,
                'status' => 'new',
                'production_stage' => 'new',
                'priority' => $data['priority'] ?? 'normal',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $order->items()->create($line);
            }

            PrintOrderEvent::query()->create([
                'print_order_id' => $order->id,
                'changed_by' => $request->user()->id,
                'from_stage' => null,
                'to_stage' => 'new',
                'note' => 'Jobcard created',
            ]);

            app(AuditLogger::class)->record('order.created', $order, [
                'job_number' => $order->job_number,
                'total' => $order->total,
            ]);

            if ($autoInvoice) {
                $invoice = $invoiceService->createForOrder($order, null, $request->user()->id);
                if ($advanceAmount > 0) {
                    $invoiceService->recordShopPayment(
                        $invoice,
                        $advanceAmount,
                        $data['payment_method'],
                        $data['payment_reference'] ?? null,
                        $request->user()->id,
                        (int) $data['payment_method_id']
                    );
                }
            }

            return $order;
        });

        return response()->json($order->load([
            'customer:id,name,phone',
            'items',
            'events.changedBy:id,name',
            'invoices.payments',
        ]), 201);
    }

    public function update(Request $request, int $orderId): JsonResponse
    {
        $order = PrintOrder::query()->findOrFail($orderId);

        if (in_array($order->status, ['delivered', 'cancelled'], true)) {
            return response()->json(['message' => 'Delivered or cancelled jobs cannot be edited.'], 409);
        }

        $data = $request->validate([
            'job_date' => ['sometimes', 'nullable', 'date'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'whatsapp_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'specifications' => ['sometimes', 'nullable', 'array'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'assigned_user_id' => ['sometimes', 'nullable', 'integer'],
            'priority' => ['sometimes', 'in:low,normal,high,urgent'],
            'round_off' => ['sometimes', 'numeric', 'min:-999999999999', 'max:999999999999'],
        ]);

        if (array_key_exists('assigned_user_id', $data) && $data['assigned_user_id'] !== null) {
            $tenant = $request->user()->currentTenant;
            $isMember = $tenant?->users()->whereKey($data['assigned_user_id'])->exists() ?? false;
            if (! $isMember) {
                throw ValidationException::withMessages(['assigned_user_id' => ['The selected user is not in this workspace.']]);
            }
        }

        if (array_key_exists('round_off', $data)) {
            $total = round((float) $order->subtotal - (float) $order->discount + (float) $order->tax + (float) $data['round_off'], 2);
            if ($total < 0) {
                throw ValidationException::withMessages(['round_off' => ['The final job total cannot be negative.']]);
            }
            $data['total'] = $total;
        }

        $order->fill($data)->save();
        app(AuditLogger::class)->record('order.updated', $order, ['fields' => array_keys($data)]);

        return response()->json($order->fresh()->load(['customer:id,name,phone', 'items']));
    }
}
