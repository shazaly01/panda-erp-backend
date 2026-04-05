<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_payroll_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');

            $table->enum('type', ['bonus', 'penalty', 'allowance', 'deduction']); // نوع الحركة
            $table->decimal('amount', 15, 2);
            $table->date('date'); // تاريخ الاستحقاق الفعلي

            $table->string('reason', 255)->nullable(); // سبب المكافأة أو الجزاء

            // لتجنب خصم أو إضافة نفس المبلغ مرتين في شهرين مختلفين
            $table->boolean('is_processed')->default(false);
            $table->unsignedBigInteger('payroll_batch_id')->nullable()->index();

            $table->foreignId('created_by')->nullable()->constrained('users');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payroll_inputs');
    }
};
