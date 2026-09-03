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
        Schema::create('inventory_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('type', 30)->default('storable')->comment('storable, raw_material, composite, service');
            $table->string('inventory_policy', 50)->default('direct_deduction')->comment('direct_deduction, auto_deduct_bom_on_sale, production_order_required');
            
            // سياسة التتبع المعيارية (بدون تتبع، بالدفعة/اللوت، بالسيريال)
            $table->string('tracking_type', 20)->default('none')->comment('none, by_batch, by_serial');
            
            // طريقة التقييم المالي المتبعة لهذا الصنف
            $table->string('valuation_method', 20)->default('avco')->comment('avco, fifo, standard');
            
            $table->decimal('cost_price', 15, 4)->default(0.0000)->comment('سعر التكلفة الافتراضي أو المعياري');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_products');
    }
};