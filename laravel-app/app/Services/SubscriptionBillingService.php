<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Support\CurrentTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SubscriptionBillingService
{
    public function requestPlan(int $planId, string $period, string $method): array
    {
        $tenantId = app(CurrentTenant::class)->requireId();
        $plan = SubscriptionPlan::query()->where('is_active', true)->findOrFail($planId);
        $amount = $period === 'yearly' ? (float) $plan->yearly_price : (float) $plan->monthly_price;

        if ($amount <= 0) {
            throw ValidationException::withMessages(['plan_id' => ['A paid plan must have a positive configured price.']]);
        }

        $activePaid = Subscription::query()->where('status', 'active')
            ->where(function ($period): void {
                $period->whereNull('current_period_ends_at')->orWhere('current_period_ends_at', '>', now());
            })->exists();

        if ($activePaid) {
            throw ValidationException::withMessages(['plan_id' => ['A paid subscription is already active. Plan changes will be handled through the subscription-management flow.']]);
        }

        return DB::transaction(function () use ($tenantId, $plan, $period, $method, $amount): array {
            $snapshot = [
                'code' => $plan->code,
                'name' => $plan->name,
                'price' => $amount,
                'billing_period' => $period,
                'limits' => $plan->limits,
                'features' => $plan->features,
            ];

            $subscription = Subscription::query()->create([
                'plan_id' => $plan->id,
                'status' => 'pending',
                'billing_period' => $period,
                'price_at_signup' => $amount,
                'currency' => 'LKR',
                'gateway' => $method === 'payhere' ? 'payhere' : 'manual',
                'plan_snapshot' => $snapshot,
            ]);

            $payment = Payment::query()->create([
                'subscription_id' => $subscription->id,
                'amount' => $amount,
                'currency' => 'LKR',
                'method' => $method,
                'status' => 'pending',
            ]);

            app(AuditLogger::class)->record('subscription.payment_requested', $subscription, [
                'payment_id' => $payment->id,
                'plan_code' => $plan->code,
                'billing_period' => $period,
                'method' => $method,
                'tenant_id' => $tenantId,
            ]);

            return ['subscription' => $subscription->load('plan'), 'payment' => $payment];
        });
    }

    public function submitManualProof(Payment $payment, UploadedFile $receipt, string $reference): Payment
    {
        if ($payment->method !== 'bank_transfer' || $payment->status !== 'pending' || ! $payment->subscription_id) {
            throw ValidationException::withMessages(['payment' => ['This payment is not awaiting bank-transfer proof.']]);
        }

        $tenantId = app(CurrentTenant::class)->requireId();
        $path = $receipt->store("private/payment-receipts/tenant-{$tenantId}", 'local');

        $payment->reference = $reference;
        $payment->receipt_path = $path;
        $payment->save();

        app(AuditLogger::class)->record('subscription.receipt_submitted', $payment, [
            'subscription_id' => $payment->subscription_id,
        ]);

        return $payment;
    }

    /** Call only after platform-admin authorization or a verified gateway notification. */
    public function approveManualPayment(int $paymentId, int $adminId): Payment
    {
        return DB::transaction(function () use ($paymentId, $adminId): Payment {
            $payment = Payment::query()->withoutGlobalScope('tenant')->lockForUpdate()->findOrFail($paymentId);
            app(CurrentTenant::class)->set((int) $payment->tenant_id);

            if ($payment->method !== 'bank_transfer' || $payment->status !== 'pending' || ! $payment->subscription_id) {
                throw ValidationException::withMessages(['payment' => ['Only pending subscription bank transfers can be approved here.']]);
            }

            if (! $payment->receipt_path || ! $payment->reference) {
                throw ValidationException::withMessages(['payment' => ['The transfer reference and receipt must be reviewed first.']]);
            }

            $subscription = Subscription::query()->lockForUpdate()->findOrFail($payment->subscription_id);
            $now = now();
            $periodEnd = $subscription->billing_period === 'yearly' ? $now->copy()->addYearNoOverflow() : $now->copy()->addMonthNoOverflow();

            // Keep only one active paid subscription per tenant.
            Subscription::query()->where('id', '!=', $subscription->id)
                ->whereIn('status', ['active', 'trialing'])
                ->update(['status' => 'cancelled', 'cancelled_at' => $now]);

            $payment->status = 'succeeded';
            $payment->approved_by = $adminId;
            $payment->paid_at = $now;
            $payment->save();

            $subscription->status = 'active';
            $subscription->starts_at = $now;
            $subscription->current_period_starts_at = $now;
            $subscription->current_period_ends_at = $periodEnd;
            $subscription->trial_ends_at = null;
            $subscription->save();

            app(AuditLogger::class)->record('subscription.payment_approved', $payment, [
                'subscription_id' => $subscription->id,
                'approved_by' => $adminId,
            ]);

            return $payment->load('subscription.plan');
        });
    }

    public function rejectManualPayment(int $paymentId, int $adminId, string $reason): Payment
    {
        return DB::transaction(function () use ($paymentId, $adminId, $reason): Payment {
            $payment = Payment::query()->withoutGlobalScope('tenant')->lockForUpdate()->findOrFail($paymentId);
            app(CurrentTenant::class)->set((int) $payment->tenant_id);

            if ($payment->method !== 'bank_transfer' || $payment->status !== 'pending' || ! $payment->subscription_id) {
                throw ValidationException::withMessages(['payment' => ['Only pending subscription bank transfers can be rejected here.']]);
            }

            $payment->status = 'failed';
            $payment->approved_by = $adminId;
            $payment->save();
            Subscription::query()->whereKey($payment->subscription_id)->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            app(AuditLogger::class)->record('subscription.payment_rejected', $payment, [
                'subscription_id' => $payment->subscription_id,
                'approved_by' => $adminId,
                'reason' => $reason,
            ]);

            return $payment->load('subscription.plan');
        });
    }

    /** Private storage path only; download should be via an authorized controller and signed URL. */
    public function receiptExists(Payment $payment): bool
    {
        return $payment->receipt_path !== null && Storage::disk('local')->exists($payment->receipt_path);
    }
}
