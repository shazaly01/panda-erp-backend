<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // 1. البيانات الشخصية
            $table->string('full_name', 200);
            // الاسم الكامل (للعرض السريع)
          //  $table->string('full_name')->virtualAs('concat(first_name, " ", last_name)');

            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable(); // Enum
            $table->string('marital_status', 20)->nullable(); // Enum
            $table->string('national_id', 50)->unique()->nullable(); // الهوية الوطنية/الإقامة

            // 2. بيانات الاتصال
            $table->string('email', 150)->unique()->nullable(); // ايميل العمل
            $table->string('phone', 50)->nullable();
            $table->string('address', 255)->nullable();

            // 3. البيانات الوظيفية
            $table->string('employee_number', 50)->unique(); // الرقم الوظيفي (EMP-001)
            $table->date('join_date'); // تاريخ التعيين
            $table->string('status', 50)->default('active'); // Enum: active, resigned...
            $table->string('employment_type', 50)->default('full_time'); // Enum

            // 4. العلاقات التنظيمية
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();

            // المدير المباشر (علاقة ذاتية Self-Reference)
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();

            // 5. الربط مع المستخدم (للدخول للنظام)
            // nullable لأن عامل النظافة قد لا يكون له مستخدم
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
