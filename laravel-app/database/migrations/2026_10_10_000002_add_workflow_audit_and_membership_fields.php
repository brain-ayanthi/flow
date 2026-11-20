<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('preferred_locale', 5)->default('en')->after('phone');
            $table->boolean('is_platform_admin')->default(false)->after('current_tenant_id');
        });

        Schema::create('print_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('print_order_id')->constrained('print_orders')->cascadeOnDelete();
            $table->string('description', 180);
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 24)->default('item');
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->json('specifications')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'print_order_id']);
        });

        Schema::create('print_order_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('print_order_id')->constrained('print_orders')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_stage', 40)->nullable();
            $table->string('to_stage', 40);
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'print_order_id', 'created_at']);
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description', 180);
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 24)->default('item');
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'invoice_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('print_order_events');
        Schema::dropIfExists('print_order_items');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['phone', 'preferred_locale', 'is_platform_admin']);
        });
    }
};
