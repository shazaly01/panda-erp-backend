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
        Schema::create('purchasing_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('receipt_number', 50)->unique(); // متوافق مع تسلسل inv_receipt
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchasing_orders')->nullOnDelete();
            $table->foreignId('supplier_id')->constrained('partners')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->restrictOnDelete();
            
            $table->date('receipt_date');
            $table->string('status', 30)->default('draft'); // draft, received, cancelled
            
            // بيانات الشحن والتسليم
            $table->string('supplier_delivery_note', 100)->nullable(); // رقم إذن تسليم المورد
            $table->string('waybill_number', 100)->nullable(); // رقم بوليصة الشحن
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();

            $table->text('notes')->nullable();

            // التدقيق والتتبع
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'status']);
            $table->index(['supplier_id', 'receipt_date']);
        });
    }

    /**
     * إلغاء الهجرة.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchasing_receipts');
    }
};