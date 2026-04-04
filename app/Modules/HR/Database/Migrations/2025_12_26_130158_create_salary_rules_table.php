<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_rules', function (Blueprint $table) {
            $table->id();

            // 1. التعريف
            $table->string('name', 150); // الاسم: بدل سكن
            $table->string('code', 50)->unique(); // الكود: HOUSING (هام جداً للمعادلات)

            // 2. التصنيف والحساب
            $table->string('category', 50); // Enum: allowance, deduction
            $table->string('type', 50);     // Enum: fixed, formula, input...

            // 3. المنطق (The Logic)
            // إذا كان fixed أو percentage، نضع القيمة هنا
            $table->decimal('value', 10, 4)->default(0);

            // إذا كان percentage، على أي كود نطبق النسبة؟ (مثلاً BASIC)
            $table->string('percentage_of_code', 50)->nullable();

            // إذا كان formula، نكتب المعادلة هنا (BASIC + HOUSING) * 0.10
            $table->text('formula_expression')->nullable();

            // 4. التوجيه المحاسبي (Integration)
            // عندما نصرف هذا البند، على أي حساب يرمي في القيود؟
            // سنخزن الـ Key الخاص بالـ Account Mapping (مثلاً: hr_housing_allowance)
            $table->string('account_mapping_key')->nullable();

            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_rules');
    }
};
