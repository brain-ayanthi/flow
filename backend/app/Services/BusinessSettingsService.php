<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BusinessSettingsService
{
    public const CATEGORIES = [
        'business', 'tax', 'product', 'contact', 'sale', 'pos', 'display_screen',
        'purchases', 'payment', 'dashboard', 'system', 'modules', 'rewards', 'prefixes', 'jobcard',
    ];

    private const PREFIX_SEQUENCE_MAP = [
        'purchase' => 'purchase',
        'purchase_return' => 'purchase_return',
        'purchase_requisition' => 'purchase_requisition',
        'purchase_order' => 'purchase_order',
        'stock_transfer' => 'stock_transfer',
        'stock_adjustment' => 'stock_adjustment',
        'sell_return' => 'sell_return',
        'expenses' => 'expense',
        'contacts' => 'contact',
        'purchase_payment' => 'purchase_payment',
        'sell_payment' => 'sell_payment',
        'expense_payment' => 'expense_payment',
        'business_location' => 'location',
        'username' => 'username',
        'subscription_number' => 'subscription',
        'draft' => 'draft',
        'sales_order' => 'sales_order',
    ];

    /** @return array<string, array<string, mixed>> */
    public function defaults(): array
    {
        return [
            'business' => [
                'name' => '', 'start_date' => null, 'phone' => null, 'email' => null, 'address' => null,
                'tax_registration_number' => null, 'currency' => 'LKR', 'currency_symbol_placement' => 'before',
                'timezone' => 'Asia/Colombo', 'default_profit_margin' => 30,
                'financial_year_start_month' => 1, 'stock_accounting_method' => 'fifo',
                'transaction_edit_days' => 30, 'date_format' => 'd/m/Y', 'time_format' => '24h',
                'currency_precision' => 2, 'quantity_precision' => 2, 'locale' => 'en', 'logo_path' => null,
            ],
            'tax' => [
                'tax1_name' => null, 'tax1_number' => null, 'tax2_name' => null, 'tax2_number' => null,
                'enable_inline_tax' => true,
            ],
            'product' => [
                'sku_prefix' => '', 'enable_product_expiry' => false, 'expiry_mode' => 'add_item_expiry',
                'enable_brands' => true, 'enable_categories' => true, 'enable_subcategories' => true,
                'enable_price_tax_info' => true, 'default_unit_id' => null, 'enable_sub_units' => false,
                'enable_racks' => false, 'enable_row' => false, 'enable_position' => false,
                'enable_warranty' => false, 'require_product_image' => false,
            ],
            'contact' => ['default_credit_limit' => null],
            'sale' => [
                'default_sale_discount' => 0, 'default_tax_group_id' => null,
                'item_addition_method' => 'increase_quantity', 'rounding_method' => 'none',
                'sale_price_is_minimum' => false, 'allow_overselling' => false,
                'enable_sales_order' => false, 'payment_term_required' => false,
                'commission_enabled' => false, 'commission_calculation_type' => 'invoice_value',
                'commission_agent_required' => false, 'commission_rate' => 0, 'payment_link_enabled' => false,
            ],
            'pos' => [
                'express_checkout' => true, 'allow_customer_creation' => true,
                'allow_walk_in_customer' => true, 'auto_print_receipt' => false,
                'clear_cart_after_sale' => true, 'require_customer_for_sale' => false,
                'auto_focus_barcode' => true,
            ],
            'display_screen' => [
                'show_product_images' => true, 'show_product_sku' => true,
                'show_product_barcode' => false, 'show_stock_quantity' => true,
                'show_selling_price' => true, 'products_per_row' => 4,
            ],
            'purchases' => [
                'allow_price_edit' => true, 'enable_purchase_status' => true,
                'enable_lot_number' => false, 'enable_purchase_order' => false,
                'enable_purchase_requisition' => false,
            ],
            'payment' => [
                'allow_split_payments' => true, 'default_payment_method' => 'cash',
                'payment_link_provider' => 'none',
                'allow_cash' => true, 'allow_bank_transfer' => true, 'allow_card' => true, 'allow_cheque' => true,
            ],
            'dashboard' => [
                'default_date_range' => 'this_month', 'refresh_interval_seconds' => 0,
                'widgets' => [
                    'sales_summary' => true, 'production_status' => true, 'cash_flow' => true,
                    'low_stock' => true, 'due_reminders' => true, 'recent_activity' => true,
                ],
            ],
            'system' => [
                'default_language' => 'en', 'auto_logout_minutes' => 60,
                'show_translation_selector' => true,
            ],
            'modules' => [
                'enabled_modules' => [
                    'purchases' => true, 'sales' => true, 'pos' => true,
                    'stock_transfers' => true, 'stock_adjustments' => true, 'expenses' => true,
                    'accounting' => true, 'tables' => false, 'modifiers' => false,
                    'service_staff' => false, 'bookings' => false, 'kitchen' => false,
                    'subscriptions' => false, 'service_types' => false,
                ],
            ],
            'rewards' => [
                'enabled' => false, 'display_name' => 'Points', 'spend_per_point' => 1,
                'min_order_total_to_earn' => 1, 'max_points_per_order' => null,
                'redeem_amount_per_point' => 1, 'min_order_total_to_redeem' => 1,
                'min_redeem_points' => null, 'max_redeem_points_per_order' => null,
                'expiry_value' => null, 'expiry_unit' => 'years',
            ],
            'prefixes' => [
                'purchase' => 'PO', 'purchase_return' => 'PR', 'purchase_requisition' => 'PRQ',
                'purchase_order' => 'PO', 'stock_transfer' => 'ST', 'stock_adjustment' => 'SA',
                'sell_return' => 'CN', 'expenses' => 'EP', 'contacts' => 'CO',
                'purchase_payment' => 'PP', 'sell_payment' => 'SP', 'expense_payment' => 'EPM',
                'business_location' => 'BL', 'username' => 'USR', 'subscription_number' => 'SUB',
                'draft' => 'DRAFT-', 'sales_order' => 'SO',
            ],
            'jobcard' => [
                'default_flex_dimension_unit' => 'ft',
                'allow_rate_override' => true,
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function all(Tenant $tenant): array
    {
        $stored = $tenant->settings ?? [];
        $settings = [];
        foreach ($this->defaults() as $category => $defaults) {
            $settings[$category] = array_replace_recursive($defaults, $stored[$category] ?? []);
        }

        $settings['business'] = array_replace($settings['business'], [
            'name' => $tenant->name,
            'phone' => $tenant->phone,
            'address' => $tenant->address,
            'tax_registration_number' => $tenant->tax_registration_number,
            'currency' => $tenant->default_currency,
            'timezone' => $tenant->timezone ?: 'Asia/Colombo',
            'default_profit_margin' => (float) $tenant->default_profit_margin,
            'financial_year_start_month' => (int) $tenant->financial_year_start_month,
            'start_date' => $tenant->business_start_date?->format('Y-m-d'),
            'locale' => $tenant->default_locale ?: 'en',
        ]);
        $settings['tax']['tax1_number'] = $settings['tax']['tax1_number'] ?: $tenant->tax_registration_number;
        $settings['business']['logo_url'] = $settings['business']['logo_path']
            ? Storage::disk('public')->url($settings['business']['logo_path'])
            : null;

        $sequences = DB::table('number_sequences')
            ->where('tenant_id', $tenant->id)
            ->whereNull('location_id')
            ->get(['sequence_type', 'prefix'])
            ->keyBy('sequence_type');
        foreach (self::PREFIX_SEQUENCE_MAP as $key => $sequenceType) {
            if ($sequences->has($sequenceType)) {
                $settings['prefixes'][$key] = $sequences[$sequenceType]->prefix ?? '';
            }
        }

        return $settings;
    }

    /** @return array<string, mixed> */
    public function options(Tenant $tenant): array
    {
        return [
            'currencies' => [
                ['code' => 'LKR', 'name' => 'Sri Lankan Rupee'], ['code' => 'USD', 'name' => 'US Dollar'],
                ['code' => 'EUR', 'name' => 'Euro'], ['code' => 'GBP', 'name' => 'Pound Sterling'],
                ['code' => 'INR', 'name' => 'Indian Rupee'], ['code' => 'AUD', 'name' => 'Australian Dollar'],
            ],
            'tax_groups' => DB::table('tax_groups')->where('tenant_id', $tenant->id)->where('is_active', true)
                ->orderBy('name')->get(['id', 'code', 'name']),
            'units' => DB::table('units')->where('tenant_id', $tenant->id)->where('is_active', true)
                ->orderBy('name')->get(['id', 'short_name', 'name']),
            'payment_methods' => DB::table('payment_methods')->where('tenant_id', $tenant->id)->where('is_active', true)
                ->orderBy('sort_order')->orderBy('name')->get(['code', 'name', 'method_type']),
        ];
    }

    /** @param array<string, mixed> $values
     *  @return array<string, array<string, mixed>>
     */
    public function update(Tenant $tenant, string $category, array $values): array
    {
        $allowed = array_keys($this->defaults()[$category]);
        if ($category === 'business') {
            $allowed = array_diff($allowed, ['logo_path']);
        }
        $values = array_intersect_key($values, array_flip($allowed));
        $stored = $tenant->settings ?? [];
        $stored[$category] = array_replace_recursive($stored[$category] ?? [], $values);

        if ($category === 'business') {
            $columns = [
                'name' => 'name', 'phone' => 'phone', 'address' => 'address',
                'tax_registration_number' => 'tax_registration_number', 'currency' => 'default_currency',
                'timezone' => 'timezone', 'default_profit_margin' => 'default_profit_margin',
                'financial_year_start_month' => 'financial_year_start_month', 'locale' => 'default_locale',
                'start_date' => 'business_start_date',
            ];
            foreach ($columns as $setting => $column) {
                if (array_key_exists($setting, $values)) {
                    $tenant->{$column} = $values[$setting];
                }
            }
        } elseif ($category === 'tax' && array_key_exists('tax1_number', $values)) {
            $tenant->tax_registration_number = $values['tax1_number'];
        }

        $tenant->settings = $stored;
        DB::transaction(function () use ($tenant, $category, $values): void {
            $tenant->save();
            if ($category === 'prefixes') {
                $this->syncNumberSequences($tenant, $values);
            }
        });

        return $this->all($tenant->fresh());
    }

    public function updateLogoPath(Tenant $tenant, string $path): array
    {
        $stored = $tenant->settings ?? [];
        $stored['business'] = array_replace_recursive(
            $stored['business'] ?? [],
            ['logo_path' => $path],
        );
        $tenant->settings = $stored;
        $tenant->save();

        return $this->all($tenant->fresh());
    }

    /** @param array<string, mixed> $values */
    private function syncNumberSequences(Tenant $tenant, array $values): void
    {
        $now = now();
        foreach (self::PREFIX_SEQUENCE_MAP as $key => $sequenceType) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $query = DB::table('number_sequences')
                ->where('tenant_id', $tenant->id)
                ->whereNull('location_id')
                ->where('sequence_type', $sequenceType);
            $existing = $query->first(['id']);
            $attributes = ['prefix' => $values[$key] === '' ? null : $values[$key], 'updated_at' => $now];
            if ($existing) {
                DB::table('number_sequences')->where('id', $existing->id)->update($attributes);
            } else {
                DB::table('number_sequences')->insert($attributes + [
                    'tenant_id' => $tenant->id,
                    'location_id' => null,
                    'sequence_type' => $sequenceType,
                    'next_number' => 1,
                    'padding' => 5,
                    'created_at' => $now,
                ]);
            }
        }
    }
}
