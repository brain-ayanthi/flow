<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Services\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $statuses = ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'];

        $quotes = Quote::query()
            ->with('customer:id,name,phone')
            ->when(in_array($request->query('status'), $statuses, true),
                fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = trim((string) $request->query('search'));
                $query->where(function ($search) use ($term): void {
                    $search->where('quote_number', 'like', "%{$term}%")
                        ->orWhere('title', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$term}%"));
                });
            })
            ->latest('issued_at')
            ->latest('id')
            ->paginate($perPage);

        return response()->json($quotes);
    }

    public function show(int $quoteId): JsonResponse
    {
        $quote = Quote::query()->with([
            'customer:id,name,phone,email',
            'items',
            'convertedOrder:id,job_number,status,total',
        ])->findOrFail($quoteId);

        return response()->json($quote);
    }

    public function store(Request $request, QuoteService $quotes): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:180'],
            'issued_at' => ['nullable', 'date'],
            'expiry_at' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:180'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit' => ['nullable', 'string', 'max:24'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'items.*.specifications' => ['nullable', 'array'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'tax' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return response()->json($quotes->create($data), 201);
    }

    public function update(Request $request, int $quoteId, QuoteService $quotes): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['sometimes', 'required', 'integer'],
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'issued_at' => ['sometimes', 'required', 'date'],
            'expiry_at' => ['sometimes', 'nullable', 'date'],
            'items' => ['sometimes', 'required', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required_with:items', 'string', 'max:180'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit' => ['nullable', 'string', 'max:24'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0', 'max:999999999999'],
            'items.*.specifications' => ['nullable', 'array'],
            'discount' => ['sometimes', 'numeric', 'min:0', 'max:999999999999'],
            'tax' => ['sometimes', 'numeric', 'min:0', 'max:999999999999'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $quote = Quote::query()->findOrFail($quoteId);

        return response()->json($quotes->updateDraft($quote, $data));
    }

    public function updateStatus(Request $request, int $quoteId, QuoteService $quotes): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:sent,accepted,rejected,expired']]);
        $quote = Quote::query()->findOrFail($quoteId);

        return response()->json($quotes->transition($quote, $data['status']));
    }

    public function convert(Request $request, int $quoteId, QuoteService $quotes): JsonResponse
    {
        $quote = Quote::query()->findOrFail($quoteId);

        return response()->json($quotes->convertToOrder($quote, $request->user()->id));
    }
}
