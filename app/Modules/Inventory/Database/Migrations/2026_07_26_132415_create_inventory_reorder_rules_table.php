<?php

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
        Schema::create('inventory_reorder_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->decimal('min_quantity', 15, 4)->default(0.0000)->comment('حد الأمان - النقطة التي ينطلق عندها إشعار الشراء');
            $table->decimal('max_quantity', 15, 4)->default(0.0000)->comment('الحد الأقصى للسعة التخزينية المسموحة');
            $table->decimal('reorder_quantity', 15, 4)->default(0.0000)->comment('الكمية الموصى بشرائها تلقائياً');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['warehouse_id', 'product_id'], 'inv_reorder_wh_prod_unique');
            $table->index(['warehouse_id', 'product_id'], 'inv_reorder_wh_prod_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_reorder_rules');
    }
};