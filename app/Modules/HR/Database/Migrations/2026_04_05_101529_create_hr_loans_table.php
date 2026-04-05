<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');

            $table->decimal('amount', 15, 2); // إجمالي مبلغ السلفة
            $table->string('reason', 255)->nullable(); // سبب السلفة

            // الربط مع الوحدة المحاسبية (سند الصرف)
            // استخدمنا unsignedBigInteger لتجنب مشاكل ترتيب الهجرات بين الوحدات (Cross-Module Constraints)
            $table->unsignedBigInteger('voucher_id')->nullable()->index();

            $table->date('deduction_start_date'); // متى يبدأ الخصم من الراتب
            $table->integer('installments_count'); // عدد الأشهر (الأقساط)

            $table->enum('status', ['pending', 'approved', 'paid_to_employee', 'completed', 'rejected'])
                  ->default('pending');

            $table->foreignId('approved_by')->nullable()->constrained('users');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_loans');
    }
};
