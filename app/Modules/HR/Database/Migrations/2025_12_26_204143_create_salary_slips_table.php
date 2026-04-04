<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_batch_id')->constrained('payroll_batches')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees');
            $table->decimal('gross_salary', 18, 4)->default(0);
            $table->decimal('total_deductions', 18, 4)->default(0);
            $table->decimal('net_salary', 18, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_slips');
    }
};
