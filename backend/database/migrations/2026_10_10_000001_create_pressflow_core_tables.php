<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 100)->unique();
            $table->char('default_currency', 3)->default('LKR');
            $table->string('default_locale', 5)->default('en');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('current_tenant_id')->nullable()->after('id')
                ->constrained('tenants')->nullOnDelete();
        });

        Schema::create('tenant_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40)->default('member');
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('phone', 30)->nullable();
            $table->string('email', 190)->nullable();
            $table->text('billing_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'phone']);
        });

        Schema::create('print_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('job_number', 32);
            $table->string('title', 180);
            $table->string('print_type', 40);
            $table->text('description')->nullable();
            $table->json('specifications')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 24)->default('item');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->dateTime('due_at')->nullable();
            $table->string('status', 32)->default('new');
            $table->string('production_stage', 40)->default('new');
            $table->string('priority', 16)->default('normal');
            $table->string('artwork_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'job_number']);
            $table->index(['tenant_id', 'status', 'due_at']);
            $table->index(['tenant_id', 'customer_id', 'created_at']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('print_order_id')->nullable()->constrained('print_orders')->nullOnDelete();
            $table->string('invoice_number', 40);
            $table->date('issued_at');
            $table->date('due_at')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->string('status', 24)->default('unpaid');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'invoice_number']);
            $table->index(['tenant_id', 'status', 'due_at']);
            $table->index(['tenant_id', 'customer_id', 'issued_at']);
        });

        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->decimal('monthly_price', 14, 2)->default(0);
            $table->decimal('yearly_price', 14, 2)->default(0);
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->json('limits')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('status', 24)->default('pending');
            $table->string('billing_period', 16)->default('monthly');
            $table->decimal('price_at_signup', 14, 2)->default(0);
            $table->char('currency', 3)->default('LKR');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('gateway', 30)->nullable();
            $table->string('gateway_reference', 120)->nullable();
            $table->json('plan_snapshot')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'current_period_ends_at']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('LKR');
            $table->string('method', 30);
            $table->string('status', 24)->default('pending');
            $table->string('reference', 120)->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('provider_event_id', 120)->nullable()->unique();
            $table->json('provider_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['invoice_id', 'status']);
            $table->index(['subscription_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('print_orders');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('tenant_user');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_tenant_id');
        });
        Schema::dropIfExists('tenants');
    }
};
