<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Services\BusinessSettingsService;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BusinessSettingsController extends Controller
{
    public function show(BusinessSettingsService $settings): JsonResponse
    {
        $tenant = $this->tenant();

        return response()->json([
            'data' => $settings->all($tenant),
            'options' => $settings->options($tenant),
        ]);
    }

    public function update(Request $request, BusinessSettingsService $settings): JsonResponse
    {
        $category = $request->validate([
            'category' => ['required', Rule::in(BusinessSettingsService::CATEGORIES)],
        ])['category'];
        if ($category === 'business' && is_string($request->input('settings.currency'))) {
            $settingsInput = $request->input('settings', []);
            $settingsInput['currency'] = strtoupper(trim($settingsInput['currency']));
            $request->merge(['settings' => $settingsInput]);
        }
        $tenantId = app(CurrentTenant::class)->requireId();
        $validated = $request->validate($this->rulesFor($category, $tenantId));
        $values = $validated['settings'] ?? [];

        if ($category === 'modules' && isset($values['enabled_modules'])) {
            $values['enabled_modules'] = array_intersect_key(
                $values['enabled_modules'],
                $settings->defaults()['modules']['enabled_modules'],
            );
        }
        if ($category === 'dashboard' && isset($values['widgets'])) {
            $values['widgets'] = array_intersect_key($values['widgets'], $settings->defaults()['dashboard']['widgets']);
        }

        $tenant = $this->tenant();
        $updated = $settings->update($tenant, $category, $values);
        app(AuditLogger::class)->record('business_settings.updated', $tenant, [
            'category' => $category,
            'fields' => array_keys($values),
        ]);

        return response()->json([
            'message' => 'Business settings saved.',
            'data' => $updated,
        ]);
    }

    public function uploadLogo(Request $request, BusinessSettingsService $settings): JsonResponse
    {
        $validated = $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);
        $tenant = $this->tenant();
        $oldPath = ($tenant->settings['business']['logo_path'] ?? null);
        $path = $validated['logo']->store('business-logos/'.$tenant->id, 'public');
        if (! $path) {
            throw ValidationException::withMessages(['logo' => ['The logo could not be stored.']]);
        }

        $data = $settings->updateLogoPath($tenant, $path);
        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }
        app(AuditLogger::class)->record('business_logo.updated', $tenant);

        return response()->json([
            'message' => 'Business logo uploaded.',
            'logo_url' => Storage::disk('public')->url($path),
            'data' => $data,
        ]);
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->findOrFail(app(CurrentTenant::class)->requireId());
    }

    /** @return array<string, array<int, mixed>> */
    private function rulesFor(string $category, int $tenantId): array
    {
        $rules = ['settings' => ['required', 'array']];
        $fields = match ($category) {
            'business' => [
                'name' => ['sometimes', 'required', 'string', 'max:160'],
                'start_date' => ['sometimes', 'nullable', 'date'],
                'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
                'email' => ['sometimes', 'nullable', 'email', 'max:190'],
                'address' => ['sometimes', 'nullable', 'string', 'max:3000'],
                'tax_registration_number' => ['sometimes', 'nullable', 'string', 'max:80'],
                'currency' => ['sometimes', 'required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
                'currency_symbol_placement' => ['sometimes', Rule::in(['before', 'after'])],
                'timezone' => ['sometimes', 'required', 'timezone'],
                'default_profit_margin' => ['sometimes', 'numeric', 'min:0', 'max:1000'],
                'financial_year_start_month' => ['sometimes', 'integer', 'min:1', 'max:12'],
                'stock_accounting_method' => ['sometimes', Rule::in(['fifo', 'lifo', 'weighted_average'])],
                'transaction_edit_days' => ['sometimes', 'integer', 'min:0', 'max:3650'],
                'date_format' => ['sometimes', Rule::in(['d/m/Y', 'm/d/Y', 'Y-m-d', 'd.m.Y'])],
                'time_format' => ['sometimes', Rule::in(['24h', '12h'])],
                'currency_precision' => ['sometimes', 'integer', 'min:0', 'max:4'],
                'quantity_precision' => ['sometimes', 'integer', 'min:0', 'max:6'],
                'locale' => ['sometimes', Rule::in(['en', 'si', 'ta'])],
            ],
            'tax' => [
                'tax1_name' => ['sometimes', 'nullable', 'string', 'max:80'],
                'tax1_number' => ['sometimes', 'nullable', 'string', 'max:80'],
                'tax2_name' => ['sometimes', 'nullable', 'string', 'max:80'],
                'tax2_number' => ['sometimes', 'nullable', 'string', 'max:80'],
                'enable_inline_tax' => ['sometimes', 'boolean'],
            ],
            'product' => [
                'sku_prefix' => ['sometimes', 'nullable', 'string', 'max:24'],
                'enable_product_expiry' => ['sometimes', 'boolean'],
                'expiry_mode' => ['sometimes', Rule::in(['add_item_expiry', 'expiry_period'])],
                'enable_brands' => ['sometimes', 'boolean'],
                'enable_categories' => ['sometimes', 'boolean'],
                'enable_subcategories' => ['sometimes', 'boolean'],
                'enable_price_tax_info' => ['sometimes', 'boolean'],
                'default_unit_id' => ['sometimes', 'nullable', 'integer', Rule::exists('units', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true))],
                'enable_sub_units' => ['sometimes', 'boolean'],
                'enable_racks' => ['sometimes', 'boolean'],
                'enable_row' => ['sometimes', 'boolean'],
                'enable_position' => ['sometimes', 'boolean'],
                'enable_warranty' => ['sometimes', 'boolean'],
                'require_product_image' => ['sometimes', 'boolean'],
            ],
            'contact' => [
                'default_credit_limit' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999'],
            ],
            'sale' => [
                'default_sale_discount' => ['sometimes', 'numeric', 'min:0', 'max:100'],
                'default_tax_group_id' => ['sometimes', 'nullable', 'integer', Rule::exists('tax_groups', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true))],
                'item_addition_method' => ['sometimes', Rule::in(['increase_quantity', 'new_line'])],
                'rounding_method' => ['sometimes', Rule::in(['none', 'nearest_whole', 'nearest_5', 'nearest_10', 'up', 'down'])],
                'sale_price_is_minimum' => ['sometimes', 'boolean'],
                'allow_overselling' => ['sometimes', 'boolean'],
                'enable_sales_order' => ['sometimes', 'boolean'],
                'payment_term_required' => ['sometimes', 'boolean'],
                'commission_enabled' => ['sometimes', 'boolean'],
                'commission_calculation_type' => ['sometimes', Rule::in(['invoice_value', 'paid_amount', 'profit'])],
                'commission_agent_required' => ['sometimes', 'boolean'],
                'commission_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
                'payment_link_enabled' => ['sometimes', 'boolean'],
            ],
            'pos' => [
                'express_checkout' => ['sometimes', 'boolean'],
                'allow_customer_creation' => ['sometimes', 'boolean'],
                'allow_walk_in_customer' => ['sometimes', 'boolean'],
                'auto_print_receipt' => ['sometimes', 'boolean'],
                'clear_cart_after_sale' => ['sometimes', 'boolean'],
                'require_customer_for_sale' => ['sometimes', 'boolean'],
                'auto_focus_barcode' => ['sometimes', 'boolean'],
            ],
            'display_screen' => [
                'show_product_images' => ['sometimes', 'boolean'],
                'show_product_sku' => ['sometimes', 'boolean'],
                'show_product_barcode' => ['sometimes', 'boolean'],
                'show_stock_quantity' => ['sometimes', 'boolean'],
                'show_selling_price' => ['sometimes', 'boolean'],
                'products_per_row' => ['sometimes', 'integer', 'min:1', 'max:8'],
            ],
            'purchases' => [
                'allow_price_edit' => ['sometimes', 'boolean'],
                'enable_purchase_status' => ['sometimes', 'boolean'],
                'enable_lot_number' => ['sometimes', 'boolean'],
                'enable_purchase_order' => ['sometimes', 'boolean'],
                'enable_purchase_requisition' => ['sometimes', 'boolean'],
            ],
            'payment' => [
                'allow_split_payments' => ['sometimes', 'boolean'],
                'default_payment_method' => ['sometimes', 'nullable', Rule::in(['cash', 'bank', 'card', 'cheque'])],
                'payment_link_provider' => ['sometimes', Rule::in(['none', 'payhere'])],
                'allow_cash' => ['sometimes', 'boolean'],
                'allow_bank_transfer' => ['sometimes', 'boolean'],
                'allow_card' => ['sometimes', 'boolean'],
                'allow_cheque' => ['sometimes', 'boolean'],
            ],
            'dashboard' => [
                'default_date_range' => ['sometimes', Rule::in(['today', 'last_7_days', 'this_month', 'financial_year'])],
                'refresh_interval_seconds' => ['sometimes', 'integer', Rule::in([0, 15, 30, 60, 300])],
                'widgets' => ['sometimes', 'array:sales_summary,production_status,cash_flow,low_stock,due_reminders,recent_activity'],
                'widgets.*' => ['boolean'],
            ],
            'system' => [
                'default_language' => ['sometimes', Rule::in(['en', 'si', 'ta'])],
                'auto_logout_minutes' => ['sometimes', 'integer', 'min:5', 'max:1440'],
                'show_translation_selector' => ['sometimes', 'boolean'],
            ],
            'modules' => [
                'enabled_modules' => ['sometimes', 'array:purchases,sales,pos,stock_transfers,stock_adjustments,expenses,accounting,tables,modifiers,service_staff,bookings,kitchen,subscriptions,service_types'],
                'enabled_modules.*' => ['boolean'],
            ],
            'rewards' => [
                'enabled' => ['sometimes', 'boolean'],
                'display_name' => ['sometimes', 'required', 'string', 'max:60'],
                'spend_per_point' => ['sometimes', 'numeric', 'gt:0', 'max:999999999'],
                'min_order_total_to_earn' => ['sometimes', 'numeric', 'min:0', 'max:999999999'],
                'max_points_per_order' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000000'],
                'redeem_amount_per_point' => ['sometimes', 'numeric', 'gt:0', 'max:999999999'],
                'min_order_total_to_redeem' => ['sometimes', 'numeric', 'min:0', 'max:999999999'],
                'min_redeem_points' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000000'],
                'max_redeem_points_per_order' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000000'],
                'expiry_value' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
                'expiry_unit' => ['sometimes', Rule::in(['days', 'months', 'years'])],
            ],
            'prefixes' => array_fill_keys(array_keys($this->prefixKeys()), ['sometimes', 'nullable', 'string', 'max:24']),
            'jobcard' => [
                'default_flex_dimension_unit' => ['sometimes', Rule::in(['ft', 'in'])],
                'allow_rate_override' => ['sometimes', 'boolean'],
            ],
            default => throw ValidationException::withMessages(['category' => ['Unknown settings section.']]),
        };

        foreach ($fields as $key => $fieldRules) {
            $rules['settings.'.$key] = $fieldRules;
        }

        return $rules;
    }

    /** @return array<string, bool> */
    private function prefixKeys(): array
    {
        return array_fill_keys([
            'purchase', 'purchase_return', 'purchase_requisition', 'purchase_order',
            'stock_transfer', 'stock_adjustment', 'sell_return', 'expenses', 'contacts',
            'purchase_payment', 'sell_payment', 'expense_payment', 'business_location',
            'username', 'subscription_number', 'draft', 'sales_order',
        ], true);
    }
}
