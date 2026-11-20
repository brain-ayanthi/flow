<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'code', 'name', 'description', 'monthly_price', 'yearly_price', 'trial_days', 'limits', 'features']);

        return response()->json(['data' => $plans]);
    }

    public function current(): JsonResponse
    {
        $subscription = Subscription::query()
            ->with('plan:id,code,name,limits,features')
            ->latest('id')
            ->first();

        return response()->json([
            'data' => $subscription,
            'can_write' => $this->isWritable($subscription),
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $payments = Payment::query()->whereNotNull('subscription_id')
            ->with('subscription.plan:id,code,name')
            ->latest('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return response()->json($payments);
    }

    public function requestPlan(Request $request, SubscriptionBillingService $billing): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'billing_period' => ['required', 'in:monthly,yearly'],
            'method' => ['required', 'in:bank_transfer,payhere'],
        ]);

        // Do not create a fake/unfinished card checkout. Enable this branch when the
        // PayHere server adapter and merchant credentials have been configured.
        if ($data['method'] === 'payhere') {
            return response()->json([
                'message' => 'PayHere checkout is not configured yet; choose bank transfer for the current pilot.',
                'code' => 'payment_gateway_not_configured',
            ], 501);
        }

        $result = $billing->requestPlan((int) $data['plan_id'], $data['billing_period'], $data['method']);

        return response()->json([
            'message' => 'Plan request created. Upload the bank-transfer receipt for platform review.',
            'data' => $result,
        ], 201);
    }

    public function submitReceipt(Request $request, int $paymentId, SubscriptionBillingService $billing): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:120'],
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $payment = Payment::query()->whereNotNull('subscription_id')->findOrFail($paymentId);
        $updated = $billing->submitManualProof($payment, $data['receipt'], $data['reference']);

        return response()->json([
            'message' => 'Receipt submitted and waiting for platform review.',
            'data' => $updated,
        ], 202);
    }

    private function isWritable(?Subscription $subscription): bool
    {
        if (! $subscription) {
            return false;
        }

        if ($subscription->status === 'active') {
            return $subscription->current_period_ends_at === null
                || $subscription->current_period_ends_at->isFuture();
        }

        return $subscription->status === 'trialing'
            && $subscription->trial_ends_at !== null
            && $subscription->trial_ends_at->isFuture();
    }
}
