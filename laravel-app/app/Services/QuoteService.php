<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PrintOrder;
use App\Models\PrintOrderEvent;
use App\Models\Quote;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuoteService
{
    public function create(array $data): Quote
    {
        $customer = Customer::query()->findOrFail($data['customer_id']);
        $issuedAt = $data['issued_at'] ?? today();
        $expiryAt = $data['expiry_at'] ?? null;
        $this->ensureDateRange($issuedAt, $expiryAt);
        $items = $this->priceItems($data['items']);
        $subtotal = $this->subtotal($items);
        $discount = round((float) ($data['discount'] ?? 0), 2);
        $tax = round((float) ($data['tax'] ?? 0), 2);
        $this->assertAmounts($subtotal, $discount, $tax);

        return DB::transaction(function () use ($data, $customer, $items, $subtotal, $discount, $tax, $issuedAt, $expiryAt): Quote {
            $tenant = $customer->tenant()->firstOrFail();
            $quote = Quote::query()->create([
                'customer_id' => $customer->id,
                'quote_number' => 'QTE-' . Str::upper(Str::random(10)),
                'title' => $data['title'],
                'status' => 'draft',
                'currency' => $tenant->default_currency,
                'issued_at' => $issuedAt,
                'expiry_at' => $expiryAt,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => round($subtotal - $discount + $tax, 2),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $quote->items()->create($item);
            }

            app(AuditLogger::class)->record('quote.created', $quote, [
                'quote_number' => $quote->quote_number,
                'total' => $quote->total,
            ]);

            return $quote->load(['customer:id,name,phone', 'items']);
        });
    }

    public function updateDraft(Quote $quote, array $data): Quote
    {
        return DB::transaction(function () use ($quote, $data): Quote {
            $locked = Quote::query()->lockForUpdate()->with('items')->findOrFail($quote->getKey());
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['quote' => ['Only draft quotes can be edited.']]);
            }

            $customer = array_key_exists('customer_id', $data)
                ? Customer::query()->findOrFail($data['customer_id'])
                : $locked->customer;
            $issuedAt = $data['issued_at'] ?? $locked->issued_at;
            $expiryAt = array_key_exists('expiry_at', $data) ? $data['expiry_at'] : $locked->expiry_at;
            $this->ensureDateRange($issuedAt, $expiryAt);
            $itemData = $data['items'] ?? $locked->items->map(fn ($item): array => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'specifications' => $item->specifications,
            ])->all();
            $items = $this->priceItems($itemData);
            $subtotal = $this->subtotal($items);
            $discount = round((float) ($data['discount'] ?? $locked->discount), 2);
            $tax = round((float) ($data['tax'] ?? $locked->tax), 2);
            $this->assertAmounts($subtotal, $discount, $tax);

            $locked->fill([
                'customer_id' => $customer->id,
                'title' => $data['title'] ?? $locked->title,
                'issued_at' => $issuedAt,
                'expiry_at' => $expiryAt,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => round($subtotal - $discount + $tax, 2),
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $locked->notes,
            ])->save();

            if (array_key_exists('items', $data)) {
                $locked->items()->delete();
                foreach ($items as $item) {
                    $locked->items()->create($item);
                }
            }

            app(AuditLogger::class)->record('quote.updated', $locked, ['fields' => array_keys($data)]);

            return $locked->fresh()->load(['customer:id,name,phone', 'items']);
        });
    }

    public function transition(Quote $quote, string $target): Quote
    {
        return DB::transaction(function () use ($quote, $target): Quote {
            $locked = Quote::query()->lockForUpdate()->findOrFail($quote->getKey());
            if ($locked->status === $target) {
                return $locked->load(['customer:id,name,phone', 'items']);
            }

            $allowed = [
                'draft' => ['sent', 'accepted', 'rejected'],
                'sent' => ['accepted', 'rejected', 'expired'],
                'accepted' => ['rejected'],
                'rejected' => [],
                'expired' => [],
                'converted' => [],
            ];
            if (! in_array($target, $allowed[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => ["Cannot move quote from {$locked->status} to {$target}."]]);
            }
            if ($target === 'accepted' && $locked->expiry_at && $locked->expiry_at->lt(today())) {
                throw ValidationException::withMessages(['status' => ['An expired quote cannot be accepted.']]);
            }

            $locked->status = $target;
            $locked->save();
            app(AuditLogger::class)->record('quote.status_changed', $locked, ['status' => $target]);

            return $locked->fresh()->load(['customer:id,name,phone', 'items']);
        });
    }

    public function convertToOrder(Quote $quote, ?int $userId = null): Quote
    {
        return DB::transaction(function () use ($quote, $userId): Quote {
            $locked = Quote::query()->lockForUpdate()->with(['items', 'customer'])->findOrFail($quote->getKey());
            if ($locked->status === 'converted' && $locked->converted_order_id) {
                return $locked->load(['convertedOrder.invoices.payments', 'items', 'customer']);
            }
            if ($locked->status !== 'accepted') {
                throw ValidationException::withMessages(['quote' => ['Only an accepted quote can be converted to a job.']]);
            }
            if ($locked->expiry_at && $locked->expiry_at->lt(today())) {
                throw ValidationException::withMessages(['quote' => ['This quote has expired and cannot be converted.']]);
            }

            $order = PrintOrder::query()->create([
                'customer_id' => $locked->customer_id,
                'job_number' => 'PF-' . Str::upper(Str::random(10)),
                'title' => $locked->title,
                'print_type' => $this->printTypeFromItems($locked->items->all()),
                'description' => null,
                'specifications' => null,
                'quantity' => 1,
                'unit' => 'job',
                'subtotal' => $locked->subtotal,
                'discount' => $locked->discount,
                'tax' => $locked->tax,
                'total' => $locked->total,
                'status' => 'new',
                'production_stage' => 'new',
                'priority' => 'normal',
                'notes' => $locked->notes,
            ]);

            foreach ($locked->items as $item) {
                $order->items()->create([
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                    'specifications' => $item->specifications,
                ]);
            }

            PrintOrderEvent::query()->create([
                'print_order_id' => $order->id,
                'changed_by' => $userId,
                'from_stage' => null,
                'to_stage' => 'new',
                'note' => 'Jobcard created from accepted quote',
            ]);

            app(InvoiceService::class)->createForOrder($order, null, $userId);

            $locked->status = 'converted';
            $locked->converted_order_id = $order->id;
            $locked->save();

            app(AuditLogger::class)->record('quote.converted_to_order', $locked, [
                'print_order_id' => $order->id,
                'job_number' => $order->job_number,
            ]);

            return $locked->fresh()->load(['convertedOrder.invoices.payments', 'items', 'customer']);
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function priceItems(array $items): array
    {
        return array_map(function (array $item): array {
            $quantity = round((float) $item['quantity'], 2);
            $unitPrice = round((float) $item['unit_price'], 2);

            return [
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? 'item',
                'unit_price' => $unitPrice,
                'line_total' => round($quantity * $unitPrice, 2),
                'specifications' => $item['specifications'] ?? null,
            ];
        }, $items);
    }

    private function subtotal(array $items): float
    {
        return round(array_sum(array_column($items, 'line_total')), 2);
    }

    private function assertAmounts(float $subtotal, float $discount, float $tax): void
    {
        if ($discount > $subtotal) {
            throw ValidationException::withMessages(['discount' => ['Discount cannot exceed the subtotal.']]);
        }
        if ($subtotal - $discount + $tax < 0) {
            throw ValidationException::withMessages(['total' => ['Quote total cannot be negative.']]);
        }
    }

    private function ensureDateRange(mixed $issuedAt, mixed $expiryAt): void
    {
        if ($expiryAt !== null && Carbon::parse($expiryAt)->startOfDay()->lt(Carbon::parse($issuedAt)->startOfDay())) {
            throw ValidationException::withMessages(['expiry_at' => ['Expiry date cannot be earlier than the issue date.']]);
        }
    }

    private function printTypeFromItems(array $items): string
    {
        $types = array_map(function ($item): string {
            $type = strtolower(trim((string) data_get($item->specifications, 'print_type', 'other')));
            return in_array($type, ['paper', 'digital-paper', 'digital_paper'], true) ? 'digital' : $type;
        }, $items);

        foreach (['flex', 'digital', 'offset', 'signage'] as $type) {
            if (in_array($type, $types, true)) {
                return $type;
            }
        }

        return 'other';
    }
}
