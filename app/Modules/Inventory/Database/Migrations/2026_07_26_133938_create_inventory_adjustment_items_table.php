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
        Schema::create('inventory_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adjustment_id')->constrained('inventory_adjustments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('product_unit_id')->constrained('inventory_product_units')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_stock_batches')->nullOnDelete();
            $table->decimal('current_quantity', 15, 4)->default(0.0000)->comment('الكمية الدفترية قبل الجرد');
            $table->decimal('actual_quantity', 15, 4)->default(0.0000)->comment('الكمية الفعلية الناتجة عن الجرد');
            $table->decimal('quantity_difference', 15, 4)->default(0.0000)->comment('الفرق بين الفعلية والدفترية');
            $table->decimal('unit_cost', 15, 4)->default(0.0000)->comment('تكلفة الوحدة أثناء الجرد');
            $table->decimal('total_cost', 15, 4)->default(0.0000)->comment('إجمالي تكلفة الفرق للبند');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['adjustment_id', 'product_id'], 'inv_adj_items_adj_prod_idx');
        });
    }

    /**
     * التراجع عن ملف الهجرة
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_items');
    }
};