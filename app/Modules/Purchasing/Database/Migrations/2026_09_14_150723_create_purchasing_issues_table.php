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
        Schema::create('purchasing_issues', function (Blueprint $table): void {
            $table->id();
            $table->string('issue_number', 50)->unique(); // متوافق مع تسلسل pur_issue
            $table->foreignId('requisition_id')->nullable()->constrained('purchasing_requisitions')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete(); // المستلم أو صاحب الطلب الأصلي

            $table->date('issue_date');

            // حالات إذن الصرف:
            // draft: مسودة ناتجة عن الفرز في انتظار تأكيد أمين المخزن
            // issued: تم الصرف الفعلي والخصم اللحظي من رصيد المخزن
            // cancelled: ملغي
            $table->string('status', 30)->default('draft');

            // إجمالي تكلفة البضاعة المنصرفة بدقة 4 خانات عشرية
            $table->decimal('total_cost', 15, 4)->default(0.0000);

            // بيانات الاعتماد والتسليم الفعلي من المستودع
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();

            $table->text('notes')->nullable();

            // التدقيق والتتبع
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'status']);
            $table->index(['requisition_id', 'status']);
            $table->index('issue_date');
        });
    }

    /**
     * إلغاء الهجرة.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchasing_issues');
    }
};