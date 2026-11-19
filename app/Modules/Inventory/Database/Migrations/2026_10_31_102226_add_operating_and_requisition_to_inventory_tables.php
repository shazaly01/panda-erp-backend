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
        Schema::table('inventory_warehouses', function (Blueprint $table): void {
            $table->boolean('is_operating')
                ->default(false)
                ->after('account_id')
                ->comment('تحديد ما إذا كان المستودع مخصصاً للتشغيل وعنابر المعالجة المباشرة');
        });

        Schema::table('inventory_transfers', function (Blueprint $table): void {
            $table->foreignId('requisition_id')
                ->nullable()
                ->after('transfer_number')
                ->constrained('purchasing_requisitions')
                ->nullOnDelete()
                ->comment('طلب الاحتياج الداخلي المصدري المنشأ عنه التحويل');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_transfers', function (Blueprint $table): void {
            $table->dropForeign(['requisition_id']);
            $table->dropColumn('requisition_id');
        });

        Schema::table('inventory_warehouses', function (Blueprint $table): void {
            $table->dropColumn('is_operating');
        });
    }
};