<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_biometric_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');

            $table->dateTime('punch_time'); // الوقت الفعلي الذي وضع فيه الموظف إصبعه
            $table->enum('punch_type', ['in', 'out', 'auto'])->default('auto'); // نوع الحركة
            $table->string('device_id', 50)->nullable(); // رقم جهاز البصمة
            $table->boolean('is_processed')->default(false); // هل قام النظام بتحويلها لـ attendance_log أم بعد؟

            $table->timestamps();
            // لا نضع softDeletes هنا لأن سجلات الآلة يجب أن تكون غير قابلة للتغيير أو الحذف (Immutable) كمرجع قانوني
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_biometric_punches');
    }
};
