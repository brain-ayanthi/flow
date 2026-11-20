<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PrintOrder;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $invoices = Invoice::query()
            ->with('customer:id,name,phone')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = trim((string) $request->query('search'));
                $query->where(function ($search) use ($term): void {
                    $search->where('invoice_number', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$term}%"));
                });
            })
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($invoices);
    }

    public function show(int $invoiceId): JsonResponse
    {
        $invoice = Invoice::query()->with([
            'customer:id,name,phone,email,billing_address',
            'items',
            'payments:id,invoice_id,amount,currency,method,status,reference,paid_at',
            'order:id,job_number,title,print_type,due_at',
        ])->findOrFail($invoiceId);

        return response()->json($invoice);
    }

    public function createFromOrder(Request $request, int $orderId, InvoiceService $service): JsonResponse
    {
        $data = $request->validate(['due_at' => ['nullable', 'date', 'after_or_equal:today']]);
        $order = PrintOrder::query()->findOrFail($orderId);
        $invoice = $service->createForOrder($order, $data['due_at'] ?? null, $request->user()->id);

        return response()->json($invoice, 201);
    }

    public function recordPayment(Request $request, int $invoiceId, InvoiceService $service): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'method' => ['required', 'in:cash,bank_transfer,card_manual,cheque'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $invoice = Invoice::query()->findOrFail($invoiceId);
        $payment = $service->recordShopPayment(
            $invoice,
            (float) $data['amount'],
            $data['method'],
            $data['reference'] ?? null,
            $request->user()->id,
        );

        return response()->json($payment, 201);
    }
}
