<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();

            // البيانات الأساسية
            $table->string('name', 50); // مثال: السنة المالية 2025
            $table->date('start_date');
            $table->date('end_date');

            // الحالة
            $table->string('status', 20)->default('open')->index();

            // منع تداخل السنوات (اختياري لكن مفضل منطقياً)
            // سنعتمد على الـ Validation لمنع التداخل، هنا نكتفي بفهرسة التواريخ
            $table->index(['start_date', 'end_date']);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_years');
    }
};
