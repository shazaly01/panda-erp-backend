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
        Schema::create('purchasing_bill_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bill_id')->constrained('purchasing_bills')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchasing_order_items')->nullOnDelete();
            $table->foreignId('receipt_item_id')->nullable()->constrained('purchasing_receipt_items')->nullOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained('inventory_product_units')->restrictOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();

            // الكميات
            $table->decimal('quantity', 15, 4);

            // الأسعار والخصومات والضرائب
            $table->decimal('unit_price', 15, 4);
            $table->decimal('discount_percentage', 5, 2)->default(0.00);
            $table->decimal('discount_amount', 15, 4)->default(0.0000);
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 4)->default(0.0000);
            $table->decimal('subtotal', 15, 4);
            $table->decimal('total', 15, 4);

            $table->text('notes')->nullable();

            // التدقيق والتتبع
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['bill_id', 'product_id']);
        });
    }

    /**
     * إلغاء الهجرة.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchasing_bill_items');
    }
};