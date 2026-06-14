<?php

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
        Schema::create('hr_visitors', function (Blueprint $table) {
            $table->id();

            // بيانات الزائر الشخصية
            $table->string('name');
            $table->string('phone');
            $table->string('national_id')->nullable(); // الرقم القومي أو جواز السفر
            $table->string('company_from')->nullable(); // جهة عمل الزائر إن وجدت

            // تفاصيل الزيارة
            $table->text('purpose')->nullable(); // سبب الزيارة

            // ربط الزائر بالموظف المُستضيف (من جدول الموظفين الحالي لديك)
            $table->foreignId('employee_id')
                  ->nullable()
                  ->constrained('employees')
                  ->onDelete('set null');

            // أمن البوابة والتحقق
            // الحالات المتوقعة: pending (تسجيل مسبق)، approved (موافق عليه)، checked_in (داخل المنشأة)، checked_out (غادر)، canceled (ملغية)
            $table->string('status')->default('pending');
            $table->string('qr_token')->unique()->nullable(); // الرمز الفريد لتوليد الـ QR Code

            // تتبع الوقت الفعلي للحركة عند البوابة
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();

            // الموظف أو رجل الأمن الذي قام بتسجيل دخول/خروج الزائر من النظام
            $table->foreignId('gatekeeper_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');

            // تتبع السجلات والحذف المرن
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_visitors');
    }
};
