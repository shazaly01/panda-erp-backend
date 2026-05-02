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
        Schema::create('hr_shift_overrides', function (Blueprint $table) {
            $table->id();

            // الموظف المستهدف
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // تاريخ الاستثناء (اليوم الذي سيتم فيه التبديل)
            $table->date('date');

            // الوردية الأصلية (التي كان يفترض أن يداوم فيها - للتوثيق فقط)
            $table->foreignId('original_shift_id')->nullable()->constrained('hr_shifts')->nullOnDelete();

            // الوردية الجديدة (التي سيداوم فيها فعلياً)
            // يمكن أن تكون Null إذا كان التجاوز هو إعفاؤه من الدوام
            $table->foreignId('new_shift_id')->nullable()->constrained('hr_shifts')->nullOnDelete();

            // سبب التجاوز
            $table->string('reason')->nullable();

            // من هو المدير/المسؤول الذي اعتمد هذا التبديل؟
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // قيد فريد: لا يمكن إدخال تجاوزين لنفس الموظف في نفس اليوم
            $table->unique(['employee_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_shift_overrides');
    }
};
