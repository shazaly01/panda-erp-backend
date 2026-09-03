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
        Schema::create('purchasing_returns', function (Blueprint $table): void {
            $table->id();
            $table->string('return_number', 50)->unique(); // متوافق مع تسلسل pur_return
            $table->foreignId('bill_id')->nullable()->constrained('purchasing_bills')->nullOnDelete();
            $table->foreignId('supplier_id')->constrained('partners')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('exchange_rate', 15, 4)->default(1.0000);

            $table->date('return_date');
            
            // حالات المرتجع: draft, posted, cancelled
            $table->string('status', 30)->default('draft');

            $table->text('return_reason')->nullable();

            // المبالغ المالية بدقة 4 خانات عشرية
            $table->decimal('subtotal', 15, 4)->default(0.0000);
            $table->decimal('tax_amount', 15, 4)->default(0.0000);
            $table->decimal('total_amount', 15, 4)->default(0.0000);

            // بيانات الترحيل والتأكيد
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();

            $table->text('notes')->nullable();

            // التدقيق والتتبع
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status']);
            $table->index('return_date');
        });
    }

    /**
     * إلغاء الهجرة.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchasing_returns');
    }
};