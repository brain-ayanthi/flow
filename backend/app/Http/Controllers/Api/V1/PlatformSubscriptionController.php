<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Services\AuditLogger;
use App\Services\SubscriptionBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PlatformSubscriptionController extends Controller
{
    public function plans(): JsonResponse
    {
        return response()->json(['data' => SubscriptionPlan::query()->orderBy('sort_order')->get()]);
    }

    public function createPlan(Request $request): JsonResponse
    {
        $data = $this->validatedPlan($request);
        $plan = SubscriptionPlan::query()->create($data);
        app(AuditLogger::class)->record('platform.plan_created', $plan, ['code' => $plan->code]);

        return response()->json(['data' => $plan], 201);
    }

    public function updatePlan(Request $request, int $planId): JsonResponse
    {
        $plan = SubscriptionPlan::query()->findOrFail($planId);
        $data = $this->validatedPlan($request, $plan->id);
        $plan->fill($data)->save();
        app(AuditLogger::class)->record('platform.plan_updated', $plan, ['fields' => array_keys($data)]);

        return response()->json(['data' => $plan->fresh()]);
    }

    public function pendingPayments(Request $request): JsonResponse
    {
        $payments = Payment::query()->withoutGlobalScope('tenant')
            ->whereNotNull('subscription_id')
            ->where('method', 'bank_transfer')
            ->where('status', 'pending')
            ->with(['tenant:id,name,slug', 'subscription.plan:id,code,name'])
            ->orderBy('created_at')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return response()->json($payments);
    }

    public function receipt(int $paymentId)
    {
        $payment = Payment::query()->withoutGlobalScope('tenant')
            ->whereNotNull('subscription_id')->findOrFail($paymentId);

        abort_unless($payment->receipt_path && Storage::disk('local')->exists($payment->receipt_path), 404);

        return Storage::disk('local')->download($payment->receipt_path, 'subscription-receipt-' . $payment->id);
    }

    public function approvePayment(Request $request, int $paymentId, SubscriptionBillingService $billing): JsonResponse
    {
        $payment = $billing->approveManualPayment($paymentId, (int) $request->user()->id);

        return response()->json(['message' => 'Bank transfer approved and subscription activated.', 'data' => $payment]);
    }

    public function rejectPayment(Request $request, int $paymentId, SubscriptionBillingService $billing): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $payment = $billing->rejectManualPayment($paymentId, (int) $request->user()->id, $data['reason']);

        return response()->json(['message' => 'Bank transfer rejected.', 'data' => $payment]);
    }

    private function validatedPlan(Request $request, ?int $planId = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('subscription_plans', 'code')->ignore($planId)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'yearly_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'limits' => ['nullable', 'array'],
            'features' => ['nullable', 'array'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);
    }
}
