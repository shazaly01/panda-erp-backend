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
        Schema::create('purchasing_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('receipt_id')->constrained('purchasing_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchasing_order_items')->nullOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained('inventory_product_units')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('inventory_warehouse_locations')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_stock_batches')->nullOnDelete();

            // الكميات بدقة 4 خانات عشرية
            $table->decimal('quantity_received', 15, 4);
            $table->decimal('quantity_accepted', 15, 4)->default(0.0000);
            $table->decimal('quantity_rejected', 15, 4)->default(0.0000);

            // تكلفة الشراء المعتمدة للحركة المخزنية
            $table->decimal('unit_cost', 15, 4)->default(0.0000);
            $table->decimal('total_cost', 15, 4)->default(0.0000);

            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();

            // التدقيق والتتبع
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['receipt_id', 'product_id']);
        });
    }

    /**
     * إلغاء الهجرة.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchasing_receipt_items');
    }
};