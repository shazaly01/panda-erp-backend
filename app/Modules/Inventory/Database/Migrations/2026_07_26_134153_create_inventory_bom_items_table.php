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
        Schema::create('inventory_bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained('inventory_boms')->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained('inventory_products')->cascadeOnDelete()->comment('المادة الخام أو المكون');
            $table->foreignId('product_unit_id')->constrained('inventory_product_units')->cascadeOnDelete();
            $table->decimal('quantity', 15, 4)->comment('الكمية المطلوبة للوحدة المنتجة');
            $table->decimal('waste_percentage', 5, 2)->default(0.00)->comment('نسبة الهدر المتوقعة %');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['bom_id', 'raw_material_id'], 'inv_bom_items_bom_raw_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_bom_items');
    }
};
