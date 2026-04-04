<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();

            // 1. المعرفات الأساسية
            // رقم القيد (مثلاً: JE-2025-0001) - يفضل أن يكون نصياً للمرونة
            $table->string('entry_number', 50)->unique()->nullable(); // Nullable لأنه يتولد عند الترحيل
            $table->date('date')->index();

            // 2. الحالة والمصدر
            $table->string('status', 20)->default('draft')->index(); // draft, posted, void
            $table->string('source', 20)->default('manual')->index(); // manual, sales, purchases...

            // 3. البيانات المالية والوصف
            $table->string('description', 255)->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();

            // 4. التدقيق (من أنشأ القيد؟)
            // نربطه بجدول users الموجود في الـ Core App
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // تاريخ الترحيل (متى تحول إلى Posted؟)
            $table->dateTime('posted_at')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
