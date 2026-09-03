<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الهجرة.
     */
    public function up(): void
    {
        Schema::create('purchasing_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_id')->constrained('purchasing_returns')->cascadeOnDelete();
            $table->foreignId('bill_item_id')->nullable()->constrained('purchasing_bill_items')->nullOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained('inventory_product_units')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('inventory_warehouse_locations')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_stock_batches')->nullOnDelete();

            // الكميات بدقة 4 خانات عشرية
            $table->decimal('quantity', 15, 4);

            // الأسعار والضرائب
            $table->decimal('unit_price', 15, 4);
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 4)->default(0.0000);
            $table->decimal('subtotal', 15, 4);
            $table->decimal('total', 15, 4);

            $table->text('reason')->nullable();
            $table->text('notes')->nullable();

            // التدقيق والتتبع
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['return_id', 'product_id']);
        });
    }

    /**
     * إلغاء الهجرة.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchasing_return_items');
    }
};