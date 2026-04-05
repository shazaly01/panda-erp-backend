<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * دالة up: تُنفذ عند تشغيل الهجرة (إضافة التعديلات)
     */
    public function up(): void
    {
        // 1. إضافة مركز التكلفة لجدول الأقسام
        Schema::table('departments', function (Blueprint $table) {
            // نضيف الحقل كـ nullable لأن الأقسام القديمة في الداتا بيز حالياً لا تملك مركز تكلفة
            $table->foreignId('cost_center_id')
                  ->nullable()
                  ->after('parent_id') // ترتيب الحقل في الداتا بيز ليكون بعد parent_id
                  ->constrained('cost_centers') // ربطه بجدول مراكز التكلفة
                  ->nullOnDelete(); // إذا تم حذف مركز التكلفة المحاسبي، لا يحذف القسم، بل يفرغ الحقل فقط
        });

        // 2. تعديل نوع رقم الموظف ليكون متوافقاً تماماً مع الأرقام الطويلة في الدليل المحاسبي
        Schema::table('employees', function (Blueprint $table) {
            // استخدام DECIMAL(18, 0) لحقول الأرقام والرموز الطويلة التي ليست قيماً مالية
            $table->decimal('employee_number', 18, 0)->change();
        });
    }

    /**
     * دالة down: تُنفذ عند التراجع عن الهجرة (حذف التعديلات)
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // حذف الارتباط (Foreign Key) ثم حذف الحقل
            $table->dropForeign(['cost_center_id']);
            $table->dropColumn('cost_center_id');
        });

        Schema::table('employees', function (Blueprint $table) {
            // إعادة الحقل إلى نوعه القديم (نفترض أنه كان نصاً string) في حال التراجع
            $table->string('employee_number')->change();
        });
    }
};
