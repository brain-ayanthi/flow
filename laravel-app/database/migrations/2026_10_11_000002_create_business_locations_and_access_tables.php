<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('timezone', 64)->default('Asia/Colombo')->after('default_locale');
            $table->unsignedTinyInteger('financial_year_start_month')->default(1);
            $table->unsignedTinyInteger('financial_year_start_day')->default(1);
            $table->decimal('default_profit_margin', 7, 2)->default(30);
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('tax_registration_number', 80)->nullable();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 60);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('module', 60);
            $table->string('name', 140);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['module', 'code']);
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['role_id', 'permission_id']);
            $table->index(['tenant_id', 'role_id']);
        });

        Schema::table('tenant_user', function (Blueprint $table): void {
            $table->foreignId('role_id')->nullable()->after('role')
                ->constrained('roles')->nullOnDelete();
            $table->index(['tenant_id', 'role_id']);
        });

        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name', 140);
            $table->string('type', 24)->default('storefront');
            $table->string('phone', 30)->nullable();
            $table->string('email', 190)->nullable();
            $table->text('address')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active', 'type']);
        });

        Schema::create('invoice_schemes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name', 100);
            $table->string('prefix', 24)->default('INV-');
            $table->string('suffix', 24)->nullable();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedTinyInteger('padding')->default(5);
            $table->string('reset_period', 24)->default('never');
            $table->string('last_reset_key', 24)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'location_id', 'code'], 'invoice_schemes_tenant_location_code_unique');
            $table->index(['tenant_id', 'location_id', 'is_active']);
        });

        Schema::create('invoice_layouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('name', 100);
            $table->json('layout')->nullable();
            $table->json('print_options')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'location_id', 'is_default']);
        });

        Schema::table('locations', function (Blueprint $table): void {
            $table->foreignId('default_invoice_scheme_id')->nullable()
                ->constrained('invoice_schemes')->nullOnDelete();
            $table->foreignId('default_invoice_layout_id')->nullable()
                ->constrained('invoice_layouts')->nullOnDelete();
        });

        Schema::create('location_user', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['location_id', 'user_id']);
            $table->index(['tenant_id', 'user_id']);
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->boolean('is_customer')->default(true);
            $table->boolean('is_supplier')->default(false);
            $table->boolean('is_default')->default(false);
            $table->string('contact_code', 40)->nullable();
            $table->string('tax_registration_number', 80)->nullable();
            $table->text('shipping_address')->nullable();
            $table->unsignedSmallInteger('payment_term_days')->default(0);
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->string('opening_balance_type', 8)->default('debit');
            $table->decimal('credit_limit', 14, 2)->nullable();
            $table->unique(['tenant_id', 'contact_code'], 'customers_tenant_contact_code_unique');
            $table->index(['tenant_id', 'is_customer', 'name']);
            $table->index(['tenant_id', 'is_supplier', 'name']);
        });

        Schema::table('print_orders', function (Blueprint $table): void {
            $table->foreignId('location_id')->nullable()->after('tenant_id')
                ->constrained('locations')->nullOnDelete();
            $table->index(['tenant_id', 'location_id', 'status']);
        });

        Schema::table('quotes', function (Blueprint $table): void {
            $table->foreignId('location_id')->nullable()->after('tenant_id')
                ->constrained('locations')->nullOnDelete();
            $table->index(['tenant_id', 'location_id', 'status']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('invoices_tenant_id_invoice_number_unique');
            $table->foreignId('location_id')->nullable()->after('tenant_id')
                ->constrained('locations')->nullOnDelete();
            $table->foreignId('invoice_scheme_id')->nullable()->constrained('invoice_schemes')->nullOnDelete();
            $table->foreignId('invoice_layout_id')->nullable()->constrained('invoice_layouts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('currency', 3)->default('LKR');
            $table->string('document_status', 16)->default('final');
            $table->string('sale_channel', 20)->default('jobcard');
            $table->json('tax_snapshot')->nullable();
            $table->timestamp('due_reminder_sent_at')->nullable();
            $table->unique(['tenant_id', 'location_id', 'invoice_number'], 'invoices_tenant_location_number_unique');
            $table->index(['tenant_id', 'location_id', 'document_status', 'issued_at']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('location_id')->nullable()->after('tenant_id')
                ->constrained('locations')->nullOnDelete();
            $table->index(['tenant_id', 'location_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_tenant_id_location_id_created_at_index');
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('invoices_tenant_location_number_unique');
            $table->dropIndex('invoices_tenant_id_location_id_document_status_issued_at_index');
            $table->dropConstrainedForeignId('location_id');
            $table->dropConstrainedForeignId('invoice_scheme_id');
            $table->dropConstrainedForeignId('invoice_layout_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['currency', 'document_status', 'sale_channel', 'tax_snapshot', 'due_reminder_sent_at']);
            $table->unique(['tenant_id', 'invoice_number']);
        });

        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropIndex('quotes_tenant_id_location_id_status_index');
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::table('print_orders', function (Blueprint $table): void {
            $table->dropIndex('print_orders_tenant_id_location_id_status_index');
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique('customers_tenant_contact_code_unique');
            $table->dropIndex('customers_tenant_id_is_customer_name_index');
            $table->dropIndex('customers_tenant_id_is_supplier_name_index');
            $table->dropColumn([
                'is_customer', 'is_supplier', 'is_default', 'contact_code', 'tax_registration_number',
                'shipping_address', 'payment_term_days', 'opening_balance', 'opening_balance_type', 'credit_limit',
            ]);
        });

        Schema::dropIfExists('location_user');

        Schema::table('locations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_invoice_scheme_id');
            $table->dropConstrainedForeignId('default_invoice_layout_id');
        });

        Schema::dropIfExists('invoice_layouts');
        Schema::dropIfExists('invoice_schemes');
        Schema::dropIfExists('locations');

        Schema::table('tenant_user', function (Blueprint $table): void {
            $table->dropIndex('tenant_user_tenant_id_role_id_index');
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn([
                'timezone', 'financial_year_start_month', 'financial_year_start_day',
                'default_profit_margin', 'phone', 'address', 'tax_registration_number',
            ]);
        });
    }
};
