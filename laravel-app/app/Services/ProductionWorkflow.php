<?php

namespace App\Services;

use App\Models\PrintOrder;
use App\Models\PrintOrderEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionWorkflow
{
    /** Initial workflow; make stages tenant-configurable after the first MVP. */
    private const TRANSITIONS = [
        'new' => ['prepress', 'cancelled'],
        'prepress' => ['proof_approved', 'printing', 'cancelled'],
        'proof_approved' => ['printing', 'cancelled'],
        'printing' => ['finishing', 'cancelled'],
        'finishing' => ['ready', 'printing', 'cancelled'],
        'ready' => ['delivered', 'finishing'],
        'delivered' => [],
        'cancelled' => [],
    ];

    private const STATUS_BY_STAGE = [
        'new' => 'new',
        'prepress' => 'proof',
        'proof_approved' => 'production',
        'printing' => 'production',
        'finishing' => 'finishing',
        'ready' => 'ready',
        'delivered' => 'delivered',
        'cancelled' => 'cancelled',
    ];

    public function move(PrintOrder $order, string $toStage, ?string $note, ?int $userId): PrintOrder
    {
        if (! array_key_exists($toStage, self::TRANSITIONS)) {
            throw ValidationException::withMessages(['stage' => ['Unknown production stage.']]);
        }

        return DB::transaction(function () use ($order, $toStage, $note, $userId): PrintOrder {
            $lockedOrder = PrintOrder::query()->lockForUpdate()->findOrFail($order->getKey());
            $fromStage = $lockedOrder->production_stage;

            if (! in_array($toStage, self::TRANSITIONS[$fromStage] ?? [], true)) {
                throw ValidationException::withMessages([
                    'stage' => ["The job cannot move from {$fromStage} to {$toStage} directly."],
                ]);
            }

            $lockedOrder->production_stage = $toStage;
            $lockedOrder->status = self::STATUS_BY_STAGE[$toStage];
            $lockedOrder->completed_at = $toStage === 'delivered' ? now() : null;
            $lockedOrder->save();

            PrintOrderEvent::query()->create([
                'print_order_id' => $lockedOrder->id,
                'changed_by' => $userId,
                'from_stage' => $fromStage,
                'to_stage' => $toStage,
                'note' => $note,
            ]);

            app(AuditLogger::class)->record('order.stage_changed', $lockedOrder, [
                'from_stage' => $fromStage,
                'to_stage' => $toStage,
            ]);

            return $lockedOrder->refresh()->load(['customer', 'items', 'events.changedBy:id,name']);
        });
    }
}
