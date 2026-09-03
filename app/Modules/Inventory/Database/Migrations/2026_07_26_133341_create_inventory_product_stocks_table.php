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
        Schema::create('inventory_product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('inventory_warehouse_locations')->nullOnDelete()->comment('موقع التخزين التفصيلي داخل المخزن');
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_stock_batches')->nullOnDelete();
            $table->decimal('quantity', 15, 4)->default(0.0000)->comment('الكمية الفعلية الموجودة بالفِعل');
            $table->decimal('reserved_quantity', 15, 4)->default(0.0000)->comment('الكمية المحجوزة لأوامر مبيعات أو إنتاج قيد التنفيذ');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'product_id'], 'inv_stocks_wh_prod_idx');
            $table->index(['warehouse_id', 'location_id', 'product_id'], 'inv_stocks_wh_loc_prod_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_product_stocks');
    }
};