<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained('sales_returns')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('sales_invoice_item_id')->nullable()->constrained('sales_invoice_items')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('inventory_units')->cascadeOnUpdate()->nullOnDelete();
            
            $table->decimal('quantity', 15, 4)->default(1.0000);
            $table->decimal('unit_price', 15, 2)->default(0.00);
            $table->decimal('cost_price', 15, 2)->default(0.00); // التكلفة لإعادة تقييم المخزون بدقة
            
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('discount_amount', 15, 2)->default(0.00);
            
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            
            $table->decimal('total_amount', 15, 2)->default(0.00);
            
            $table->string('item_condition', 30)->default('good'); // good, damaged, scrap
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
    }
};