<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_serials', function (Blueprint $table) {
            $table->decimal('tare_weight', 15, 4)
                ->nullable()
                ->after('serial_number')
                ->comment('الوزن الفارغ للأسطوانة/الوعاء الحديدي بدون غاز (كجم)');

            $table->decimal('gross_weight', 15, 4)
                ->nullable()
                ->after('tare_weight')
                ->comment('الوزن الإجمالي القائم الحالي شاملاً الأسطوانة والغاز (كجم)');

            $table->decimal('current_gas_weight', 15, 4)
                ->nullable()
                ->after('gross_weight')
                ->comment('صافي وزن غاز الكلور الفعلي المتواجد داخل الأسطوانة حالياً (كجم)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_serials', function (Blueprint $table) {
            $table->dropColumn([
                'tare_weight',
                'gross_weight',
                'current_gas_weight',
            ]);
        });
    }
};