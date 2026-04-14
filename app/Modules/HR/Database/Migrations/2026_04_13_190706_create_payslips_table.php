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
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();

            // الربط مع المسير (الدفعة) والموظف
            $table->foreignId('payroll_batch_id')->constrained('payroll_batches')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // الأرقام المالية (الصورة التذكارية الثابتة)
            $table->decimal('basic_salary', 15, 2);
            $table->decimal('total_allowances', 15, 2);
            $table->decimal('total_deductions', 15, 2);
            $table->decimal('net_salary', 15, 2);

            // حقل JSON لنحفظ بداخله تفاصيل كل بدلة وخصم باسمها وقيمتها
            $table->json('details');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
