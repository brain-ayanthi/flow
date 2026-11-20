<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PrintOrder;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function createForOrder(PrintOrder $order, ?string $dueAt, ?int $userId): Invoice
    {
        return DB::transaction(function () use ($order, $dueAt, $userId): Invoice {
            $lockedOrder = PrintOrder::query()->lockForUpdate()->findOrFail($order->getKey());
            $customer = Customer::query()->findOrFail($lockedOrder->customer_id);

            if (in_array($lockedOrder->status, ['cancelled'], true)) {
                throw ValidationException::withMessages(['order_id' => ['A cancelled job cannot be invoiced.']]);
            }

            if ($lockedOrder->invoices()->exists()) {
                throw ValidationException::withMessages(['order_id' => ['This job already has an invoice. Use its existing invoice for part-payments.']]);
            }

            $invoice = Invoice::query()->create([
                'customer_id' => $customer->id,
                'print_order_id' => $lockedOrder->id,
                'invoice_number' => 'INV-' . Str::upper(Str::random(10)),
                'issued_at' => today(),
                'due_at' => $dueAt,
                'subtotal' => $lockedOrder->subtotal,
                'discount' => $lockedOrder->discount,
                'tax' => $lockedOrder->tax,
                'round_off' => $lockedOrder->round_off,
                'total' => $lockedOrder->total,
                'paid_total' => 0,
                'status' => 'unpaid',
            ]);

            $orderItems = $lockedOrder->items()->get();
            if ($orderItems->isNotEmpty()) {
                foreach ($orderItems as $orderItem) {
                    $invoice->items()->create([
                        'description' => $orderItem->description,
                        'quantity' => $orderItem->quantity,
                        'unit' => $orderItem->unit,
                        'unit_price' => $orderItem->unit_price,
                        'line_total' => $orderItem->line_total,
                    ]);
                }
            } else {
                $invoice->items()->create([
                    'description' => $lockedOrder->title,
                    'quantity' => $lockedOrder->quantity,
                    'unit' => $lockedOrder->unit,
                    'unit_price' => (float) $lockedOrder->subtotal / max((float) $lockedOrder->quantity, 0.01),
                    'line_total' => $lockedOrder->subtotal,
                ]);
            }

            app(AuditLogger::class)->record('invoice.created', $invoice, [
                'print_order_id' => $lockedOrder->id,
                'total' => $invoice->total,
            ]);

            return $invoice->load(['customer', 'items', 'order']);
        });
    }

    public function recordShopPayment(
        Invoice $invoice,
        float $amount,
        string $method,
        ?string $reference,
        ?int $userId,
        ?int $paymentMethodId = null
    ): Payment {
        if (! in_array($method, ['cash', 'bank_transfer', 'card_manual', 'cheque'], true)) {
            throw ValidationException::withMessages(['method' => ['Unsupported shop-invoice payment method.']]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => ['Payment amount must be greater than zero.']]);
        }

        $paymentMethod = $paymentMethodId === null
            ? null
            : PaymentMethod::query()->where('is_active', true)->findOrFail($paymentMethodId);

        return DB::transaction(function () use ($invoice, $amount, $method, $reference, $userId, $paymentMethod): Payment {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
            $outstanding = round((float) $lockedInvoice->total - (float) $lockedInvoice->paid_total, 2);
            $amount = round($amount, 2);

            if ($amount > $outstanding) {
                throw ValidationException::withMessages(['amount' => ['Payment cannot exceed the outstanding invoice balance.']]);
            }

            $payment = Payment::query()->create([
                'invoice_id' => $lockedInvoice->id,
                'location_id' => $paymentMethod?->location_id,
                'contact_id' => $lockedInvoice->customer_id,
                'payment_method_id' => $paymentMethod?->id,
                'amount' => $amount,
                'currency' => app(CurrentTenant::class)->id() ? $lockedInvoice->tenant->default_currency : 'LKR',
                'method' => $method,
                'status' => 'succeeded',
                'reference' => $reference,
                'approved_by' => $userId,
                'paid_at' => now(),
            ]);

            $lockedInvoice->paid_total = round((float) $lockedInvoice->paid_total + $amount, 2);
            $lockedInvoice->status = (float) $lockedInvoice->paid_total >= (float) $lockedInvoice->total ? 'paid' : 'partial';
            $lockedInvoice->save();

            app(AuditLogger::class)->record('invoice.payment_recorded', $payment, [
                'invoice_id' => $lockedInvoice->id,
                'amount' => $amount,
                'method' => $method,
            ]);

            return $payment->load('invoice');
        });
    }
}
