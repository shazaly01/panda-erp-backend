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
        Schema::create('inventory_production_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('inventory_production_orders')->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('product_unit_id')->constrained('inventory_product_units')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_stock_batches')->nullOnDelete();
            $table->decimal('planned_quantity', 15, 4)->comment('الكمية المستهدفة للاستهلاك حسب BOM');
            $table->decimal('actual_quantity', 15, 4)->default(0.0000)->comment('الكمية الفعلية المستهلكة عند التشغيل');
            $table->decimal('unit_cost', 15, 4)->default(0.0000)->comment('تكلفة وحدة المادة الخام وقت الصرف');
            $table->decimal('total_cost', 15, 4)->default(0.0000)->comment('إجمالي التكلفة المصروفة لهذا المكون');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['production_order_id', 'raw_material_id'], 'inv_poid_raw_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_production_order_items');
    }
};
