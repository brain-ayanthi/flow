<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobcard_masters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('code', 48)->nullable();
            $table->string('name', 120);
            $table->string('unit', 24)->nullable();
            $table->decimal('rate', 14, 2)->nullable();
            $table->string('charge_mode', 24)->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'id'], 'jobcard_masters_tenant_id_id_unique');
            $table->unique(['tenant_id', 'type', 'name'], 'jobcard_masters_tenant_type_name_unique');
            $table->index(['tenant_id', 'type', 'is_active'], 'jobcard_masters_tenant_type_active_idx');
        });

        Schema::create('jobcard_digital_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('particular_id');
            $table->unsignedBigInteger('media_id');
            $table->unsignedBigInteger('size_id');
            $table->unsignedBigInteger('side_id');
            $table->decimal('rate', 14, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(
                ['tenant_id', 'particular_id', 'media_id', 'size_id', 'side_id'],
                'jobcard_digital_rates_tenant_combo_unique'
            );
            $table->index(['tenant_id', 'particular_id'], 'jobcard_digital_rates_particular_idx');
            $table->index(['tenant_id', 'media_id'], 'jobcard_digital_rates_media_idx');
            $table->index(['tenant_id', 'size_id'], 'jobcard_digital_rates_size_idx');
            $table->index(['tenant_id', 'side_id'], 'jobcard_digital_rates_side_idx');
            $table->foreign(['tenant_id', 'particular_id'], 'jobcard_digital_rates_particular_fk')
                ->references(['tenant_id', 'id'])->on('jobcard_masters')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'media_id'], 'jobcard_digital_rates_media_fk')
                ->references(['tenant_id', 'id'])->on('jobcard_masters')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'size_id'], 'jobcard_digital_rates_size_fk')
                ->references(['tenant_id', 'id'])->on('jobcard_masters')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'side_id'], 'jobcard_digital_rates_side_fk')
                ->references(['tenant_id', 'id'])->on('jobcard_masters')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobcard_digital_rates');
        Schema::dropIfExists('jobcard_masters');
    }
};
