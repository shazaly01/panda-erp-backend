<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. جدول هياكل الرواتب (القوالب)
        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique(); // مثال: الهيكل الإداري القياسي
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        // 2. جدول الربط (Pivot Table) بين الهيكل والقواعد
        Schema::create('structure_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('structure_id')->constrained('salary_structures')->cascadeOnDelete();
            $table->foreignId('rule_id')->constrained('salary_rules')->cascadeOnDelete();

            // الترتيب جوهري جداً!
            // يجب أن نحسب (الأساسي) أولاً -> ثم (البدلات) -> ثم (الصافي)
            // لذلك سنعطي لكل قاعدة رقماً تسلسلياً داخل الهيكل
            $table->integer('sequence')->default(0);

            // لا يجوز تكرار نفس القاعدة في نفس الهيكل مرتين
            $table->unique(['structure_id', 'rule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('structure_rules');
        Schema::dropIfExists('salary_structures');
    }
};
