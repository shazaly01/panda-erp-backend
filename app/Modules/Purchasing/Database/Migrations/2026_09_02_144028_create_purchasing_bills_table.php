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
        Schema::create('purchasing_bills', function (Blueprint $table): void {
            $table->id();
            $table->string('bill_number', 50)->unique(); // متوافق مع تسلسل pur_bill
            $table->string('supplier_bill_number', 100)->nullable(); // رقم فاتورة المورد الضريبية الأصلية
            $table->foreignId('supplier_id')->constrained('partners')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchasing_orders')->nullOnDelete();
            $table->foreignId('receipt_id')->nullable()->constrained('purchasing_receipts')->nullOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('exchange_rate', 15, 4)->default(1.0000);

            $table->date('bill_date');
            $table->date('due_date');
            
            // حالات الفاتورة: draft, posted, paid, partially_paid, cancelled
            $table->string('status', 30)->default('draft');

            // المبالغ المالية بدقة 4 خانات عشرية
            $table->decimal('subtotal', 15, 4)->default(0.0000);
            $table->string('discount_type', 20)->default('fixed'); // fixed, percentage
            $table->decimal('discount_value', 15, 4)->default(0.0000);
            $table->decimal('discount_amount', 15, 4)->default(0.0000);
            $table->decimal('tax_amount', 15, 4)->default(0.0000);
            $table->decimal('shipping_cost', 15, 4)->default(0.0000);
            $table->decimal('total_amount', 15, 4)->default(0.0000);
            $table->decimal('paid_amount', 15, 4)->default(0.0000);
            $table->decimal('remaining_amount', 15, 4)->default(0.0000);

            // بيانات الترحيل المحاسبي
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();

            $table->text('notes')->nullable();

            // التدقيق والتتبع
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status']);
            $table->index('bill_date');
            $table->index('due_date');
        });
    }

    /**
     * إلغاء الهجرة.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchasing_bills');
    }
};