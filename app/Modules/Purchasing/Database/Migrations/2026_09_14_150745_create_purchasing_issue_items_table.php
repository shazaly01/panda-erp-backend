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
        Schema::create('purchasing_issue_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained('purchasing_issues')->cascadeOnDelete();
            $table->foreignId('requisition_item_id')->nullable()->constrained('purchasing_requisition_items')->nullOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained('inventory_product_units')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('inventory_warehouse_locations')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_stock_batches')->nullOnDelete();

            // الكمية المنصرفة بدقة 4 خانات عشرية
            $table->decimal('quantity', 15, 4);

            // تكلفة الوحدة والتكلفة الإجمالية للحركة المخزنية
            $table->decimal('unit_cost', 15, 4)->default(0.0000);
            $table->decimal('total_cost', 15, 4)->default(0.0000);

            $table->text('notes')->nullable();

            // التدقيق والتتبع
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['issue_id', 'product_id']);
            $table->index('requisition_item_id');
        });
    }

    /**
     * إلغاء الهجرة.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchasing_issue_items');
    }
};