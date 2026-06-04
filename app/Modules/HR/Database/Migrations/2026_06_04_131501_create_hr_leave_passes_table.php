<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hr_leave_passes', function (Blueprint $table) {
            $table->id();

            // ربط الإذن بالموظف طالب الخروج المؤقت
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade');

            $table->date('date');
            $table->string('reason')->nullable();

            // النطاق الزمني المخطط والمطلوب للإذن (ساعات الدوام في نفس اليوم)
            $table->time('requested_leave_at');
            $table->time('requested_return_at');

            // الطوابع الزمنية الفعلية للحركة اللحظية عند البوابة الخارجيّة (تُسجل بواسطة الأمن)
            $table->timestamp('actual_leave_at')->nullable();
            $table->timestamp('actual_return_at')->nullable();

            // كود التحقق الفريد (يُولد كـ Barcode/QR ليمسحه الحارس عند البوابة)
            $table->string('pass_code')->unique();

            // الحالات الحركية للإذن
            // pending: بانتظار المشرف | approved: وافق المشرف | rejected: رُفض | out: خارج المؤسسة حالياً | returned: عاد للمؤسسة | expired: انتهى ولم يُستغل
            $table->string('status')->default('pending');

            // حقول التدقيق والرقابة (Audit & Security Fields)
            // المشرف المحاسب/الإداري المعتمد للطلب برمجياً
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('employees')
                ->onDelete('set null');

            // مستخدم الأمن (الحارس) الذي أثبت وبصم خروج الموظف فعلياً
            $table->foreignId('gate_checked_out_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // مستخدم الأمن (الحارس) الذي أثبت وبصم عودة الموظف فعلياً لداخل الأسوار
            $table->foreignId('gate_checked_in_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // الدعم الصارم للحذف المرن والتدقيق الزمني للأنظمة الكبرى
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_leave_passes');
    }
};
