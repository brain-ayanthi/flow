<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\InvoiceLayout;
use App\Models\InvoiceScheme;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;

class BusinessSetupService
{
    /** Create a usable default location, numbering/layout, walk-in contact and base roles for a business. */
    public function provision(Tenant $tenant, ?User $owner = null): void
    {
        app(CurrentTenant::class)->set((int) $tenant->id);

        DB::transaction(function () use ($tenant, $owner): void {
            $this->ensurePermissionCatalog();

            $location = Location::query()->firstOrCreate(
                ['code' => 'MAIN'],
                [
                    'name' => 'Main Store',
                    'type' => 'storefront',
                    'timezone' => $tenant->timezone ?: 'Asia/Colombo',
                    'is_active' => true,
                ],
            );

            $scheme = InvoiceScheme::query()->firstOrCreate(
                ['location_id' => $location->id, 'code' => 'DEFAULT'],
                [
                    'name' => 'Standard Invoice',
                    'prefix' => 'INV-',
                    'next_number' => 1,
                    'padding' => 6,
                    'reset_period' => 'never',
                    'is_active' => true,
                ],
            );

            $layout = InvoiceLayout::query()->firstOrCreate(
                ['location_id' => $location->id, 'name' => 'Standard A4'],
                [
                    'layout' => ['paper' => 'A4', 'orientation' => 'portrait', 'show_logo' => true],
                    'print_options' => ['copies' => 1, 'show_tax_breakdown' => true],
                    'is_default' => true,
                    'is_active' => true,
                ],
            );

            $location->fill([
                'default_invoice_scheme_id' => $scheme->id,
                'default_invoice_layout_id' => $layout->id,
            ])->save();

            $walkIn = Customer::query()->where('is_default', true)->first();
            if (! $walkIn) {
                Customer::query()->create([
                    'name' => 'Walk-In Customer',
                    'contact_code' => 'WALK-IN',
                    'is_customer' => true,
                    'is_supplier' => false,
                    'is_default' => true,
                    'payment_term_days' => 0,
                ]);
            }

            foreach ([
                ['code' => 'cash', 'name' => 'Cash', 'method_type' => 'cash'],
                ['code' => 'bank', 'name' => 'Bank Transfer', 'method_type' => 'bank'],
                ['code' => 'card', 'name' => 'Card', 'method_type' => 'card'],
                ['code' => 'cheque', 'name' => 'Cheque', 'method_type' => 'cheque'],
            ] as $method) {
                PaymentMethod::query()->firstOrCreate(
                    ['location_id' => $location->id, 'code' => $method['code']],
                    ['name' => $method['name'], 'method_type' => $method['method_type'], 'is_active' => true],
                );
            }

            $permissions = Permission::query()->pluck('id', 'code');
            $admin = Role::query()->firstOrCreate(
                ['code' => 'admin'],
                ['name' => 'Admin', 'description' => 'Full access to this business.', 'is_system' => true, 'is_active' => true],
            );
            $cashier = Role::query()->firstOrCreate(
                ['code' => 'cashier'],
                ['name' => 'Cashier', 'description' => 'Point-of-sale and receipt access.', 'is_system' => true, 'is_active' => true],
            );

            $adminPivot = $permissions->values()->mapWithKeys(fn ($permissionId) => [
                $permissionId => ['tenant_id' => $tenant->id],
            ])->all();
            $admin->permissions()->sync($adminPivot);
            $cashierCodes = [
                'dashboard.view', 'contacts.view', 'contacts.manage', 'products.view',
                'sales.view', 'sales.manage', 'inventory.view', 'taxes.view',
            ];
            $cashierIds = collect($cashierCodes)
                ->map(fn ($code) => $permissions[$code] ?? null)
                ->filter()
                ->unique()
                ->values();
            $cashierPivot = $cashierIds->mapWithKeys(fn ($permissionId) => [
                $permissionId => ['tenant_id' => $tenant->id],
            ])->all();
            $cashier->permissions()->sync($cashierPivot);

            if ($owner) {
                $tenant->users()->updateExistingPivot($owner->id, [
                    'role' => 'owner',
                    'role_id' => $admin->id,
                ]);
                $location->users()->syncWithoutDetaching([$owner->id => ['tenant_id' => $tenant->id]]);
            }
        });
    }

    private function ensurePermissionCatalog(): void
    {
        $catalog = [
            'dashboard.view' => ['dashboard', 'View dashboard'],
            'contacts.view' => ['contacts', 'View customers and suppliers'],
            'contacts.manage' => ['contacts', 'Create and edit customers and suppliers'],
            'products.view' => ['products', 'View products and prices'],
            'products.manage' => ['products', 'Manage products, units and variations'],
            'purchases.view' => ['purchases', 'View purchases'],
            'purchases.manage' => ['purchases', 'Manage purchases and supplier payments'],
            'sales.view' => ['sales', 'View sales and invoices'],
            'sales.manage' => ['sales', 'Create and manage POS sales'],
            'inventory.view' => ['inventory', 'View stock by location'],
            'inventory.manage' => ['inventory', 'Adjust and transfer stock'],
            'taxes.view' => ['taxes', 'View tax rates and groups'],
            'taxes.manage' => ['taxes', 'Create and change tax rates and groups'],
            'expenses.view' => ['expenses', 'View expenses'],
            'expenses.manage' => ['expenses', 'Manage expenses and categories'],
            'accounting.view' => ['accounting', 'View accounting records'],
            'accounting.manage' => ['accounting', 'Manage accounts and journals'],
            'reports.view' => ['reports', 'View business reports'],
            'users.view' => ['users', 'View business users and roles'],
            'users.manage' => ['users', 'Invite users and manage roles'],
            'locations.view' => ['locations', 'View business locations'],
            'locations.manage' => ['locations', 'Manage locations and invoice layouts'],
            'settings.manage' => ['settings', 'Manage business settings'],
        ];

        foreach ($catalog as $code => [$module, $name]) {
            Permission::query()->firstOrCreate(
                ['code' => $code],
                ['module' => $module, 'name' => $name],
            );
        }
    }
}
