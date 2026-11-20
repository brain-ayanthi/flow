<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BusinessSetupService;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
            'shop_name' => ['required', 'string', 'max:160'],
            'locale' => ['nullable', 'in:en,si,ta'],
        ]);

        [$user, $tenant] = DB::transaction(function () use ($data): array {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'preferred_locale' => $data['locale'] ?? 'en',
                'password' => Hash::make($data['password']),
            ]);

            $slug = Str::slug($data['shop_name']) ?: 'print-shop';
            $tenant = Tenant::query()->create([
                'name' => $data['shop_name'],
                'slug' => $slug . '-' . Str::lower(Str::random(6)),
                'default_currency' => 'LKR',
                'default_locale' => $data['locale'] ?? 'en',
            ]);

            $tenant->users()->attach($user->id, ['role' => 'owner']);
            $user->current_tenant_id = $tenant->id;
            $user->save();

            app(CurrentTenant::class)->set((int) $tenant->id);
            app(BusinessSetupService::class)->provision($tenant, $user);
            $trialPlan = SubscriptionPlan::query()->where('code', 'starter')->where('is_active', true)->first();

            if ($trialPlan && $trialPlan->trial_days > 0) {
                $trialEndsAt = now()->addDays($trialPlan->trial_days);
                Subscription::query()->create([
                    'plan_id' => $trialPlan->id,
                    'status' => 'trialing',
                    'billing_period' => 'monthly',
                    'price_at_signup' => $trialPlan->monthly_price,
                    'currency' => 'LKR',
                    'starts_at' => now(),
                    'trial_ends_at' => $trialEndsAt,
                    'current_period_starts_at' => now(),
                    'current_period_ends_at' => $trialEndsAt,
                    'plan_snapshot' => [
                        'code' => $trialPlan->code,
                        'name' => $trialPlan->name,
                        'monthly_price' => $trialPlan->monthly_price,
                        'limits' => $trialPlan->limits,
                        'features' => $trialPlan->features,
                    ],
                ]);
            }

            return [$user, $tenant];
        });

        $token = $user->createToken('pressflow-mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->only(['id', 'name', 'email', 'phone', 'preferred_locale']),
            'workspace' => $tenant->only(['id', 'name', 'slug', 'default_currency', 'default_locale']),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::query()->where('email', mb_strtolower($data['email']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['The supplied credentials are incorrect.']]);
        }

        if (! $user->current_tenant_id || ! $user->tenants()->whereKey($user->current_tenant_id)->exists()) {
            $firstTenant = $user->tenants()->first();
            if ($firstTenant) {
                $user->current_tenant_id = $firstTenant->id;
                $user->save();
            }
        }

        $token = $user->createToken($data['device_name'] ?? 'pressflow-mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->only(['id', 'name', 'email', 'phone', 'preferred_locale']),
            'workspace' => $user->currentTenant?->only(['id', 'name', 'slug', 'default_currency', 'default_locale']),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('currentTenant');
        $membership = $user->tenants()->whereKey($user->current_tenant_id)->first();

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'phone', 'preferred_locale']),
            'workspace' => $user->currentTenant?->only(['id', 'name', 'slug', 'default_currency', 'default_locale']),
            'role' => $membership?->pivot?->role,
        ]);
    }

    public function workspaces(Request $request): JsonResponse
    {
        $workspaces = $request->user()->tenants()->orderBy('tenants.name')->get()
            ->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'role' => $tenant->pivot->role,
                'selected' => (int) $tenant->id === (int) $request->user()->current_tenant_id,
            ]);

        return response()->json(['data' => $workspaces]);
    }

    public function switchWorkspace(Request $request): JsonResponse
    {
        $data = $request->validate(['tenant_id' => ['required', 'integer']]);
        $tenant = $request->user()->tenants()->whereKey($data['tenant_id'])->firstOrFail();

        $request->user()->current_tenant_id = $tenant->id;
        $request->user()->save();

        return response()->json([
            'message' => 'Workspace selected.',
            'workspace' => $tenant->only(['id', 'name', 'slug', 'default_currency', 'default_locale']),
            'role' => $tenant->pivot->role,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }
}
