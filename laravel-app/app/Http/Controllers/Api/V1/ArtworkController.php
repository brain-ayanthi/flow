<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PrintOrder;
use App\Services\AuditLogger;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ArtworkController extends Controller
{
    public function upload(Request $request, int $orderId)
    {
        $data = $request->validate([
            'artwork' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:102400'],
        ]);

        $order = PrintOrder::query()->findOrFail($orderId);
        $path = $data['artwork']->store(
            'private/artwork/tenant-' . app(CurrentTenant::class)->requireId() . '/orders/' . $order->id,
            'local'
        );

        $oldPath = $order->artwork_path;
        $order->artwork_path = $path;
        $order->save();

        if ($oldPath && str_starts_with($oldPath, 'private/artwork/')) {
            Storage::disk('local')->delete($oldPath);
        }

        app(AuditLogger::class)->record('order.artwork_uploaded', $order, ['file_name' => $data['artwork']->getClientOriginalName()]);

        return response()->json(['message' => 'Artwork uploaded securely.', 'artwork_available' => true], 201);
    }

    public function download(int $orderId)
    {
        $order = PrintOrder::query()->findOrFail($orderId);
        abort_unless($order->artwork_path && Storage::disk('local')->exists($order->artwork_path), 404);

        return Storage::disk('local')->download($order->artwork_path);
    }
}
