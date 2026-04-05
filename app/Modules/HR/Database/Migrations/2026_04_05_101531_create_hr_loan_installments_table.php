<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('hr_loans')->cascadeOnDelete();

            $table->decimal('amount', 15, 2); // مبلغ القسط
            $table->date('due_month'); // شهر الاستحقاق (مثلاً: 2026-05-01)

            $table->enum('status', ['pending', 'deducted', 'skipped'])->default('pending');

            // بمجرد خصم القسط في مسير الرواتب، يتم ربطه برقم المسير هنا كدليل قاطع
            $table->unsignedBigInteger('payroll_batch_id')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_loan_installments');
    }
};
