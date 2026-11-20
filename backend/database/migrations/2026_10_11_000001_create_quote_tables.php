<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('converted_order_id')->nullable()->constrained('print_orders')->nullOnDelete();
            $table->string('quote_number', 40);
            $table->string('title', 180);
            $table->string('status', 24)->default('draft');
            $table->char('currency', 3)->default('LKR');
            $table->date('issued_at');
            $table->date('expiry_at')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'quote_number']);
            $table->index(['tenant_id', 'status', 'expiry_at']);
            $table->index(['tenant_id', 'customer_id', 'issued_at']);
        });

        Schema::create('quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->string('description', 180);
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 24)->default('item');
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->json('specifications')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'quote_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
    }
};
