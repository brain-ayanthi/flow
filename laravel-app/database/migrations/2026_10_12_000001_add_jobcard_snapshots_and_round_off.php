<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_orders', function (Blueprint $table): void {
            $table->date('job_date')->nullable()->after('customer_id');
            $table->string('contact_name', 160)->nullable()->after('job_date');
            $table->string('contact_phone', 30)->nullable()->after('contact_name');
            $table->string('whatsapp_phone', 30)->nullable()->after('contact_phone');
            $table->decimal('round_off', 14, 2)->default(0)->after('tax');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->decimal('round_off', 14, 2)->default(0)->after('tax');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('round_off');
        });

        Schema::table('print_orders', function (Blueprint $table): void {
            $table->dropColumn(['job_date', 'contact_name', 'contact_phone', 'whatsapp_phone', 'round_off']);
        });
    }
};
