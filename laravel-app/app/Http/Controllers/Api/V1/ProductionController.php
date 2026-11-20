<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PrintOrder;
use App\Services\ProductionWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    public function move(Request $request, int $orderId, ProductionWorkflow $workflow): JsonResponse
    {
        $data = $request->validate([
            'stage' => ['required', 'string', 'in:new,prepress,proof_approved,printing,finishing,ready,delivered,cancelled'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        // Resolve after tenant middleware runs; implicit route binding is intentionally avoided.
        $order = PrintOrder::query()->findOrFail($orderId);
        $updated = $workflow->move($order, $data['stage'], $data['note'] ?? null, $request->user()->id);

        return response()->json($updated);
    }
}
