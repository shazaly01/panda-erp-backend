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
        Schema::create('inventory_product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('inventory_units')->cascadeOnDelete();
            $table->decimal('conversion_factor', 15, 6)->default(1.000000)->comment('معامل التحويل بالنسبة للوحدة الأساسية');
            $table->boolean('is_base_unit')->default(false)->comment('الوحدة الأساسية الصغرى');
            $table->boolean('is_purchase_unit')->default(false)->comment('وحدة الشراء الافتراضية');
            $table->boolean('is_sale_unit')->default(false)->comment('وحدة البيع الافتراضية');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_id', 'unit_id']);
            $table->index(['product_id', 'is_base_unit']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_product_units');
    }
};
