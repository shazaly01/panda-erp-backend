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
        Schema::create('inventory_boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete()->comment('المنتج التام أو المركب النهائي');
            $table->foreignId('product_unit_id')->constrained('inventory_product_units')->cascadeOnDelete()->comment('وحدة إنتاج المنتج النهائي');
            $table->string('code')->nullable()->unique();
            $table->string('name');
            $table->decimal('quantity', 15, 4)->default(1.0000)->comment('كمية المخرجات الناتجة من القائمة');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_boms');
    }
};