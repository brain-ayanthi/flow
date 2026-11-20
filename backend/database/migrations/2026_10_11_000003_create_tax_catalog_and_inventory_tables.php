<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('calculation_type', 16)->default('percentage');
            $table->decimal('rate', 14, 4);
            $table->boolean('is_compound')->default(false);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active', 'valid_from', 'valid_until']);
        });

        Schema::create('tax_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_inclusive')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active', 'name']);
        });

        Schema::create('tax_group_rates', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_group_id')->constrained('tax_groups')->cascadeOnDelete();
            $table->foreignId('tax_rate_id')->constrained('tax_rates')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->primary(['tax_group_id', 'tax_rate_id']);
            $table->index(['tenant_id', 'tax_group_id', 'sort_order']);
        });

        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'parent_id', 'is_active']);
        });

        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('short_name', 24);
            $table->boolean('allow_decimal')->default(true);
            $table->foreignId('base_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('base_multiplier', 18, 6)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'short_name']);
            $table->index(['tenant_id', 'is_active', 'name']);
        });

        Schema::create('variation_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->json('attributes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('sequence_type', 32);
            $table->string('prefix', 24)->nullable();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedTinyInteger('padding')->default(5);
            $table->timestamps();
            $table->unique(['tenant_id', 'location_id', 'sequence_type'], 'number_sequences_tenant_location_type_unique');
        });

        Schema::create('barcode_label_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('paper_size', 24)->default('custom');
            $table->decimal('label_width_mm', 7, 2)->default(50);
            $table->decimal('label_height_mm', 7, 2)->default(25);
            $table->unsignedSmallInteger('columns')->default(2);
            $table->unsignedSmallInteger('rows')->default(10);
            $table->decimal('horizontal_gap_mm', 7, 2)->default(2);
            $table->decimal('vertical_gap_mm', 7, 2)->default(2);
            $table->string('barcode_symbology', 16)->default('CODE128');
            $table->boolean('show_product_name')->default(true);
            $table->boolean('show_sku')->default(true);
            $table->boolean('show_price')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('tax_group_id')->nullable()->constrained('tax_groups')->nullOnDelete();
            $table->foreignId('variation_template_id')->nullable()->constrained('variation_templates')->nullOnDelete();
            $table->string('product_type', 16)->default('single');
            $table->string('sku', 80)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->decimal('purchase_price', 14, 4)->default(0);
            $table->decimal('selling_price', 14, 4)->default(0);
            $table->decimal('profit_margin', 8, 4)->default(0);
            $table->boolean('manage_stock')->default(true);
            $table->decimal('low_stock_alert', 14, 4)->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'sku']);
            $table->unique(['tenant_id', 'barcode']);
            $table->index(['tenant_id', 'product_type', 'is_active', 'name']);
            $table->index(['tenant_id', 'category_id', 'subcategory_id']);
        });

        Schema::create('product_variations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('tax_group_id')->nullable()->constrained('tax_groups')->nullOnDelete();
            $table->string('sku', 80)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->string('name', 180);
            $table->json('attributes');
            $table->decimal('purchase_price', 14, 4)->default(0);
            $table->decimal('selling_price', 14, 4)->default(0);
            $table->decimal('profit_margin', 8, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'sku']);
            $table->unique(['tenant_id', 'barcode']);
            $table->index(['tenant_id', 'product_id', 'is_active']);
        });

        Schema::create('product_location_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variation_id')->nullable()->constrained('product_variations')->cascadeOnDelete();
            $table->string('variation_key', 32)->default('single');
            $table->decimal('quantity_on_hand', 14, 4)->default(0);
            $table->decimal('quantity_reserved', 14, 4)->default(0);
            $table->decimal('alert_quantity', 14, 4)->nullable();
            $table->decimal('selling_price_override', 14, 4)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'location_id', 'product_id', 'variation_key'], 'product_location_stock_identity_unique');
            $table->index(['tenant_id', 'location_id', 'quantity_on_hand'], 'product_location_stock_qty_idx');
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variation_id')->nullable()->constrained('product_variations')->nullOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('movement_type', 24);
            $table->string('direction', 8);
            $table->decimal('quantity', 14, 4);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('idempotency_key', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'location_id', 'product_id', 'created_at'], 'stock_movement_product_date_idx');
            $table->index(['tenant_id', 'source_type', 'source_id']);
        });

        Schema::create('stock_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('adjustment_number', 48);
            $table->string('status', 16)->default('draft');
            $table->date('adjustment_date');
            $table->string('reason', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'adjustment_number']);
            $table->index(['tenant_id', 'location_id', 'status', 'adjustment_date'], 'stock_adjustment_location_date_idx');
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variation_id')->nullable()->constrained('product_variations')->nullOnDelete();
            $table->decimal('quantity_before', 14, 4)->default(0);
            $table->decimal('quantity_counted', 14, 4)->default(0);
            $table->decimal('quantity_change', 14, 4)->default(0);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'stock_adjustment_id']);
        });

        Schema::create('stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('transfer_number', 48);
            $table->string('status', 16)->default('draft');
            $table->date('transfer_date');
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'transfer_number']);
            $table->index(['tenant_id', 'from_location_id', 'to_location_id', 'status'], 'stock_transfer_locations_status_idx');
        });

        Schema::create('stock_transfer_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variation_id')->nullable()->constrained('product_variations')->nullOnDelete();
            $table->decimal('quantity_requested', 14, 4);
            $table->decimal('quantity_received', 14, 4)->default(0);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'stock_transfer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_location_stocks');
        Schema::dropIfExists('product_variations');
        Schema::dropIfExists('products');
        Schema::dropIfExists('barcode_label_settings');
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('variation_templates');
        Schema::dropIfExists('units');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('tax_group_rates');
        Schema::dropIfExists('tax_groups');
        Schema::dropIfExists('tax_rates');
    }
};
