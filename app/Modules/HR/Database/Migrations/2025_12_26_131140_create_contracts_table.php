<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();

            // 1. الأطراف
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // 2. الهيكل المالي (أهم حقل!)
            // يحدد ما هي البدلات التي يستحقها هذا الموظف
            $table->foreignId('salary_structure_id')->constrained('salary_structures');

            // 3. البيانات المالية
            // هذا الرقم هو الـ "BASIC" الذي ستستخدمه كل المعادلات
            $table->decimal('basic_salary', 12, 2);

            // 4. تواريخ العقد
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null = عقد مفتوح

            // 5. حالة العقد
            $table->boolean('is_active')->default(true);

            // 6. نسخة من العقد (PDF)
            $table->string('attachment_path')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
