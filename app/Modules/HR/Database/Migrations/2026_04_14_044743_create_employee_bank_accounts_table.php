<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            $table->string('bank_name'); // اسم البنك (مثل: بنك الخرطوم)

            // ملاحظة هندسية: رغم أننا نستخدم DECIMAL(18,0) للأرقام الوظيفية والرموز،
            // إلا أن أرقام الحسابات البنكية يجب أن تكون بصيغة STRING (نص)
            // لأنها قد تبدأ بصفر (مثل 001234) والـ DECIMAL سيقوم بحذف الأصفار التي على اليسار وتخريب رقم الحساب.
            $table->string('account_number');

            $table->string('iban')->nullable(); // الآيبان الدولي إن وُجد

            $table->boolean('is_primary')->default(true); // هل هو الحساب الأساسي لتحويل الراتب؟

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_bank_accounts');
    }
};
