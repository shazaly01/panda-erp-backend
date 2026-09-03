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
        Schema::create('inventory_production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('bom_id')->constrained('inventory_boms')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            
            // فصل مخزن سحب الخام عن مخزن إيداع المنتج النهائي
            $table->foreignId('raw_materials_warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete()->comment('مخزن سحب المواد الخام للمكونات');
            $table->foreignId('finished_goods_warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete()->comment('مخزن إيداع المنتج التام بعد التصنيع');
            
            // ربط الدفعة المنتَجة للمنتج النهائي
            $table->foreignId('produced_batch_id')->nullable()->constrained('inventory_stock_batches')->nullOnDelete()->comment('الدفعة التخزينية للمنتج النهائي عند التصنيع');

            $table->decimal('planned_quantity', 15, 4)->comment('الكمية المخطط إنتاجها');
            $table->decimal('actual_quantity', 15, 4)->default(0.0000)->comment('الكمية الفعلية المنتجة');
            
            // التكاليف التشغيلية غير المباشرة (أجور فنيين، شحن، مصروفات تشغيل)
            $table->decimal('additional_costs', 15, 4)->default(0.0000)->comment('التكاليف المباشرة والتشغيلية الإضافية على أمر الإنتاج');
            
            $table->string('status', 30)->default('draft')->comment('draft, in_progress, completed, cancelled');
            $table->date('production_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['raw_materials_warehouse_id', 'status'], 'inv_prod_ord_raw_wh_stat_idx');
            $table->index(['finished_goods_warehouse_id', 'status'], 'inv_prod_ord_fg_wh_stat_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_production_orders');
    }
};