<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PrintOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobSheetController extends Controller
{
    public function show(Request $request, int $orderId): View
    {
        $order = PrintOrder::query()
            ->with([
                'customer:id,name,phone,email,billing_address,shipping_address',
                'items',
                'invoices:id,print_order_id,total,paid_total,status',
                'invoices.payments:id,invoice_id,payment_method_id,amount,method,status,paid_at',
                'invoices.payments.paymentMethod:id,name,method_type',
                'events.changedBy:id,name',
            ])
            ->findOrFail($orderId);

        $groups = ['flex' => [], 'digital' => [], 'other' => []];
        $printItems = [];
        $formatNumber = static function (mixed $value): string {
            if (! is_numeric($value)) {
                return '—';
            }

            return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        };
        $displayText = static function (mixed $value, string $fallback = '—'): string {
            if (! is_scalar($value)) {
                return $fallback;
            }

            $text = trim((string) $value);

            return $text !== '' ? $text : $fallback;
        };

        foreach ($order->items as $item) {
            $specifications = is_array($item->specifications) ? $item->specifications : [];
            $rawCategory = strtolower(trim((string) ($specifications['category'] ?? $specifications['print_type'] ?? $order->print_type ?? 'other')));
            $category = match ($rawCategory) {
                'flex' => 'flex',
                'digital', 'paper', 'digital-paper', 'digital_paper', 'offset' => 'digital',
                default => 'other',
            };

            $dimensionUnit = ($specifications['dimension_unit'] ?? '') === 'in' ? 'in' : (($specifications['dimension_unit'] ?? '') === 'ft' ? 'ft' : '');
            $width = $formatNumber($specifications['width'] ?? null);
            $height = $formatNumber($specifications['height'] ?? null);
            $productionSize = $category === 'flex' && $width !== '—' && $height !== '—'
                ? trim($width . ' ' . $dimensionUnit . ' × ' . $height . ' ' . $dimensionUnit)
                : '';
            $hasFlexPieceCount = $category === 'flex' && isset($specifications['pieces']);
            $quantityValue = $hasFlexPieceCount ? $specifications['pieces'] : $item->quantity;
            $quantityUnit = $hasFlexPieceCount ? 'pcs' : $displayText($item->unit, '');
            $quantity = $formatNumber($quantityValue) . ($quantityUnit !== '' ? ' ' . $quantityUnit : '');
            $remark = trim((string) ($specifications['remark'] ?? ''));

            $material = match ($category) {
                'flex' => $displayText($specifications['media_name'] ?? $specifications['media'] ?? null, $item->description),
                'digital' => $displayText($specifications['media_name'] ?? $specifications['media'] ?? null),
                default => $displayText($specifications['material'] ?? null, ''),
            };
            $name = match ($category) {
                'flex' => $displayText($specifications['media_name'] ?? $specifications['media'] ?? null, $item->description),
                'digital' => $displayText($specifications['particular_name'] ?? $specifications['particular'] ?? null, $item->description),
                default => $displayText($specifications['other_item_name'] ?? $specifications['item_name'] ?? null, $item->description),
            };
            $size = match ($category) {
                'flex' => $productionSize !== '' ? $productionSize : '—',
                'digital' => $displayText($specifications['size_name'] ?? $specifications['size'] ?? null),
                default => $displayText($specifications['size'] ?? null),
            };
            $fabrication = $displayText($specifications['fabrication_name'] ?? $specifications['fabrication'] ?? null);
            $media = $displayText($specifications['media_name'] ?? $specifications['media'] ?? null, $category === 'flex' ? $name : '—');
            $side = $displayText($specifications['side_name'] ?? $specifications['side'] ?? $specifications['sides'] ?? null);

            $line = [
                'category' => $category,
                'category_label' => match ($category) {
                    'flex' => 'Flex Items',
                    'digital' => 'Paper / Digital Items',
                    default => 'Other Items',
                },
                'item' => $name,
                'detail' => implode(' · ', array_values(array_filter([
                    $name,
                    $fabrication !== '—' ? $fabrication : '',
                    $remark,
                ], static fn (string $value): bool => $value !== ''))),
                'material' => $material,
                'fabrication' => $fabrication,
                'width' => $width,
                'height' => $height,
                'dimension_unit' => $dimensionUnit,
                'size' => $size,
                'side' => $side,
                'quantity' => $quantity,
                'remark' => $remark,
                'description' => $item->description,
            ];

            $printItems[] = $line;
            $groups[$category][] = match ($category) {
                'flex' => [
                    'item' => $line['item'],
                    'remark' => $remark,
                    'fabrication' => $fabrication,
                    'width' => $width,
                    'height' => $height,
                    'dimension_unit' => $dimensionUnit,
                    'quantity' => $quantity,
                ],
                'digital' => [
                    'item' => $line['item'],
                    'media' => $media,
                    'size' => $size,
                    'side' => $side,
                    'quantity' => $quantity,
                    'remark' => $remark,
                ],
                default => [
                    'item' => $line['item'],
                    'size' => $size,
                    'quantity' => $quantity,
                    'remark' => $remark,
                ],
            };
        }

        $titles = ['flex' => 'Flex Items', 'digital' => 'Paper / Digital Items', 'other' => 'Other Items'];
        $sections = [];
        foreach ($groups as $key => $items) {
            if ($items !== []) {
                $sections[] = ['key' => $key, 'title' => $titles[$key], 'items' => $items];
            }
        }
        if ($sections === []) {
            $sections[] = ['key' => 'other', 'title' => 'Job Details', 'items' => []];
        }

        $productGroups = [
            'flexBanner' => [],
            'flexBoard' => [],
            'pvcSticker' => [],
            'signBoard' => [],
            'generic' => [],
        ];
        foreach ($printItems as $printItem) {
            $searchText = mb_strtolower(implode(' ', [
                $printItem['item'],
                $printItem['material'],
                $printItem['fabrication'],
                $printItem['size'],
            ]));

            if (str_contains($searchText, 'sign') && str_contains($searchText, 'board')) {
                $productGroups['signBoard'][] = $printItem;
            } elseif (($printItem['category'] === 'flex' || str_contains($searchText, 'flex')) && str_contains($searchText, 'board')) {
                $productGroups['flexBoard'][] = $printItem;
            } elseif (str_contains($searchText, 'pvc') || str_contains($searchText, 'sticker')) {
                $productGroups['pvcSticker'][] = $printItem;
            } elseif ($printItem['category'] === 'flex' || str_contains($searchText, 'banner')) {
                $productGroups['flexBanner'][] = $printItem;
            } else {
                $productGroups['generic'][] = $printItem;
            }
        }

        $rank = [
            'new' => 0,
            'prepress' => 1,
            'proof_approved' => 2,
            'printing' => 3,
            'finishing' => 4,
            'ready' => 5,
            'delivered' => 6,
            'cancelled' => 0,
        ][$order->production_stage] ?? 0;
        $processDefinitions = [
            ['Design / Artwork', 1, 2],
            ['Proof Approval', 1, 2],
            ['Printing', 3, 4],
            ['Lamination / Coating', 4, 5],
            ['Cutting', 4, 5],
            ['Folding', 4, 5],
            ['Binding / Finishing', 4, 5],
            ['Quality Check', 4, 5],
            ['Ready for Delivery / Collection', 5, 6],
        ];
        $trackingRows = array_map(static function (array $process) use ($rank): array {
            [$name, $startedAt, $completedAt] = $process;
            $status = $rank >= $completedAt ? 'completed' : ($rank >= $startedAt ? 'in_progress' : 'pending');

            return ['name' => $name, 'status' => $status];
        }, $processDefinitions);

        $workspace = $request->user()->currentTenant;
        $customer = $order->customer;
        $successfulPayments = $order->invoices
            ->flatMap(fn ($invoice) => $invoice->payments)
            ->filter(fn ($payment) => $payment->status === 'succeeded');
        $advanceAmount = (float) $order->invoices->sum(fn ($invoice) => (float) $invoice->paid_total);
        $paymentMethods = $successfulPayments
            ->map(fn ($payment) => strtolower((string) $payment->method))
            ->unique()
            ->values();
        $paymentChecks = [
            'cash' => $paymentMethods->contains('cash'),
            'bank' => $paymentMethods->contains('bank_transfer'),
            'other' => $paymentMethods->contains(fn ($method) => ! in_array($method, ['cash', 'bank_transfer'], true)),
        ];
        $paymentMethodNames = $successfulPayments
            ->map(fn ($payment) => $payment->paymentMethod?->name ?: str_replace('_', ' ', ucfirst((string) $payment->method)))
            ->unique()
            ->implode(', ');
        $currency = $workspace?->default_currency ?: 'LKR';
        $materialSummary = implode(', ', array_unique(array_map(
            static fn (array $line): string => trim($line['material']) !== '' && $line['material'] !== '—' ? $line['material'] : $line['item'],
            $printItems
        )));
        $sizeSummary = implode(', ', array_unique(array_map(static fn (array $line): string => $line['size'], $printItems)));
        $quantitySummary = implode('; ', array_map(static fn (array $line): string => $line['quantity'], $printItems));
        $productSummary = implode(', ', array_map(static fn (array $line): string => $line['item'], $printItems));
        $selectedJobType = match ($order->print_type) {
            'offset' => 'offset',
            'digital', 'flex', 'signage' => 'digital',
            default => 'other',
        };
        $deliveryAddress = $customer?->shipping_address ?: $customer?->billing_address;

        return view('app.job-sheet', [
            'order' => $order,
            'workspace' => $workspace,
            'customer' => $customer,
            'preparedBy' => $request->user()->name,
            'sections' => $sections,
            'printItems' => $printItems,
            'productGroups' => $productGroups,
            'trackingRows' => $trackingRows,
            'paymentChecks' => $paymentChecks,
            'paymentMethodNames' => $paymentMethodNames,
            'advanceAmount' => $advanceAmount,
            'balanceAmount' => max(0, (float) $order->total - $advanceAmount),
            'currencyLabel' => $currency === 'LKR' ? 'Rs.' : $currency,
            'materialSummary' => $materialSummary,
            'sizeSummary' => $sizeSummary,
            'quantitySummary' => $quantitySummary,
            'productSummary' => $productSummary,
            'selectedJobType' => $selectedJobType,
            'deliveryAddress' => $deliveryAddress,
            'autoPrint' => $request->boolean('autoprint'),
        ]);
    }
}
