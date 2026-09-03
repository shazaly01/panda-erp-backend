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
        Schema::create('inventory_product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained('inventory_price_lists')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('product_unit_id')->constrained('inventory_product_units')->cascadeOnDelete();
            $table->decimal('price', 15, 4)->default(0.0000);
            $table->decimal('min_quantity', 15, 4)->default(1.0000)->comment('الحد الأدنى للكمية للاستفادة من السعر');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['price_list_id', 'product_id', 'product_unit_id'], 'inv_prices_unique');
            $table->index(['product_id', 'price_list_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_product_prices');
    }
};
