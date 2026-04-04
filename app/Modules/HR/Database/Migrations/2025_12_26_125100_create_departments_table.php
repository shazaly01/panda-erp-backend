<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();

            // 1. البيانات التعريفية
            $table->string('name', 150); // اسم الإدارة/القسم
            $table->string('code', 50)->unique()->nullable(); // كود (HR-01)

            // 2. النوع (من الـ Enum الذي أنشأناه)
            // يحدد هل هذا السجل (إدارة عليا) أم (قسم فرعي)
            $table->string('type', 50)->default('department')->index();

            // 3. الشجرة (Nested Set)
            // هذا السطر السحري يضيف columns: _lft, _rgt, parent_id
            $table->nestedSet();

            // 4. حالة القسم
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable(); // وصف مهام القسم

            // ملاحظة: لم نضف manager_id (مدير القسم) الآن لتجنب الخطأ
            // لأن جدول الموظفين لم ينشأ بعد. سنضيفه لاحقاً.

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
