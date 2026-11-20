<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name', 100);
            $table->string('method_type', 24)->default('cash');
            $table->json('settings')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'location_id', 'code'], 'payment_methods_tenant_location_code_unique');
            $table->index(['tenant_id', 'location_id', 'is_active']);
        });

        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purchase_number', 48);
            $table->string('supplier_invoice_number', 80)->nullable();
            $table->string('document_status', 16)->default('draft');
            $table->string('payment_status', 16)->default('unpaid');
            $table->char('currency', 3)->default('LKR');
            $table->date('purchase_date');
            $table->date('due_at')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->json('tax_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('due_reminder_sent_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'location_id', 'purchase_number'], 'purchases_tenant_location_number_unique');
            $table->index(['tenant_id', 'location_id', 'purchase_date']);
            $table->index(['tenant_id', 'supplier_id', 'payment_status', 'due_at']);
        });

        Schema::create('purchase_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variation_id')->nullable()->constrained('product_variations')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('tax_group_id')->nullable()->constrained('tax_groups')->nullOnDelete();
            $table->string('description', 180);
            $table->string('unit_name', 24)->default('item');
            $table->decimal('quantity', 14, 4);
            $table->decimal('unit_cost', 14, 4);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->json('tax_snapshot')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'purchase_id']);
            $table->index(['tenant_id', 'product_id', 'product_variation_id']);
        });

        Schema::create('purchase_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('LKR');
            $table->string('method', 24);
            $table->string('reference', 120)->nullable();
            $table->timestamp('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'location_id', 'paid_at']);
            $table->index(['tenant_id', 'purchase_id', 'paid_at']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('contact_id')->nullable()->after('customer_id')
                ->constrained('customers')->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->index(['tenant_id', 'location_id', 'sale_channel', 'document_status'], 'invoices_tenant_location_channel_doc_idx');
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_variation_id')->nullable()->constrained('product_variations')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('tax_group_id')->nullable()->constrained('tax_groups')->nullOnDelete();
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->json('tax_snapshot')->nullable();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('contact_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->index(['tenant_id', 'contact_id', 'created_at']);
        });

        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->foreignId('parent_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'parent_id', 'is_active']);
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->foreignId('payee_contact_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('tax_group_id')->nullable()->constrained('tax_groups')->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('expense_number', 48);
            $table->string('status', 16)->default('paid');
            $table->string('description', 180);
            $table->date('expense_date');
            $table->date('due_at')->nullable();
            $table->char('currency', 3)->default('LKR');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('payment_method', 24)->nullable();
            $table->string('reference', 120)->nullable();
            $table->string('receipt_path')->nullable();
            $table->json('tax_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'expense_number']);
            $table->index(['tenant_id', 'location_id', 'expense_date']);
            $table->index(['tenant_id', 'expense_category_id', 'expense_date']);
        });

        Schema::create('contact_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('contact_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entry_type', 32);
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->date('transaction_date');
            $table->date('due_at')->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->char('currency', 3)->default('LKR');
            $table->string('reference', 120)->nullable();
            $table->string('idempotency_key', 120)->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'contact_id', 'transaction_date'], 'contact_ledger_contact_date_idx');
            $table->index(['tenant_id', 'due_at', 'reminder_sent_at']);
            $table->index(['tenant_id', 'source_type', 'source_id']);
        });

        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name', 140);
            $table->string('account_type', 24);
            $table->string('normal_balance', 8)->default('debit');
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'account_type', 'is_active']);
        });

        Schema::create('accounting_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 16)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'starts_on', 'ends_on']);
        });

        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('journal_number', 48);
            $table->date('entry_date');
            $table->string('status', 16)->default('draft');
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference', 120)->nullable();
            $table->text('narration')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'journal_number']);
            $table->index(['tenant_id', 'location_id', 'entry_date', 'status']);
        });

        Schema::create('journal_entry_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->unsignedSmallInteger('line_number')->default(1);
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->char('currency', 3)->default('LKR');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'journal_entry_id', 'line_number']);
            $table->index(['tenant_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('contact_ledger_entries');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_tenant_id_contact_id_created_at_index');
            $table->dropConstrainedForeignId('contact_id');
            $table->dropConstrainedForeignId('payment_method_id');
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_id');
            $table->dropConstrainedForeignId('product_variation_id');
            $table->dropConstrainedForeignId('unit_id');
            $table->dropConstrainedForeignId('tax_group_id');
            $table->dropColumn(['discount_amount', 'tax_amount', 'tax_snapshot']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('invoices_tenant_location_channel_doc_idx');
            $table->dropConstrainedForeignId('contact_id');
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropConstrainedForeignId('finalized_by');
            $table->dropColumn(['finalized_at']);
        });

        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('payment_methods');
    }
};
