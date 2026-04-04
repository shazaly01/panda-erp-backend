<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boxes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // اسم الخزينة (خزينة الفرع الرئيسي)

            // الربط المحاسبي (أهم حقل)
            // هذا الحقل سيحمل ID الحساب المتفرع من (نقدية بالصندوق)
            $table->foreignId('account_id')
                  ->constrained('accounts')
                  ->restrictOnDelete(); // ممنوع حذف الحساب طالما الخزينة موجودة

            // الفرع التابعة له (اختياري حسب نظامك)
            $table->foreignId('branch_id')->nullable();

            // العملة الافتراضية لهذه الخزينة
            $table->foreignId('currency_id')->constrained('currencies');

            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boxes');
    }
};
