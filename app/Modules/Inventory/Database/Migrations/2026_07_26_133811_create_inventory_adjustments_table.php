<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل ملف الهجرة
     */
    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->string('adjustment_number')->unique();
            $table->date('adjustment_date');
            $table->string('type', 30)->comment('opening_balance, physical_count, damage, loss');
            $table->string('status', 30)->default('draft')->comment('draft, approved, cancelled');
            $table->decimal('total_cost', 15, 4)->default(0.0000)->comment('إجمالي الأثر المالي للتسوية');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'status'], 'inv_adj_wh_stat_idx');
        });
    }

    /**
     * التراجع عن ملف الهجرة
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};