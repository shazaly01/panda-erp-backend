<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_invoices', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            }

            if (!Schema::hasColumn('sales_invoices', 'customer_name_text')) {
                $table->string('customer_name_text')->nullable()->after('partner_id');
            }
        });

        // توليد UUID لأي فواتير موجودة مسبقاً لتجنب القيم الفارغة
        DB::table('sales_invoices')
            ->whereNull('uuid')
            ->orderBy('id')
            ->each(function ($invoice) {
                DB::table('sales_invoices')
                    ->where('id', $invoice->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('sales_invoices', 'uuid')) {
                $table->dropUnique(['uuid']);
                $table->dropColumn('uuid');
            }

            if (Schema::hasColumn('sales_invoices', 'customer_name_text')) {
                $table->dropColumn('customer_name_text');
            }
        });
    }
};