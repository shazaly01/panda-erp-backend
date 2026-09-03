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
        Schema::create('purchasing_requisition_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requisition_id')->constrained('purchasing_requisitions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained('inventory_product_units')->restrictOnDelete();

            // الكميات بدقة 4 خانات عشرية
            $table->decimal('quantity_requested', 15, 4);
            $table->decimal('quantity_approved', 15, 4)->default(0.0000);
            $table->decimal('quantity_ordered', 15, 4)->default(0.0000);
            $table->decimal('estimated_unit_cost', 15, 4)->default(0.0000);

            $table->text('specifications')->nullable();
            $table->text('notes')->nullable();

            // التدقيق والتتبع
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['requisition_id', 'product_id']);
        });
    }

    /**
     * إلغاء الهجرة.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchasing_requisition_items');
    }
};