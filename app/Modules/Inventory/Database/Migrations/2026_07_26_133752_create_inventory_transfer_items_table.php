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
        Schema::create('inventory_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('inventory_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('product_unit_id')->constrained('inventory_product_units')->cascadeOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('inventory_warehouse_locations')->nullOnDelete()->comment('رف/موقع السحب من المخزن المصدر');
            $table->foreignId('to_location_id')->nullable()->constrained('inventory_warehouse_locations')->nullOnDelete()->comment('رف/موقع الإيداع في المخزن الهدف');
            $table->foreignId('batch_id')->nullable()->constrained('inventory_stock_batches')->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4)->default(0.0000)->comment('التكلفة الفردية للصنف وقت التحويل');
            $table->decimal('total_cost', 15, 4)->default(0.0000)->comment('إجمالي تكلفة الكمية المحولة');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['transfer_id', 'product_id'], 'inv_trf_items_trf_prod_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transfer_items');
    }
};