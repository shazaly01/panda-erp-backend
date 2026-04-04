<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // كود الحساب
            $table->string('name', 150);          // اسم الحساب

            // نوع الحساب (أصول، خصوم...)
            $table->string('type', 50)->index();

            // طبيعة الحساب (مدين/دائن) - مهم للتوازن
            $table->string('nature', 20);

            // [تعديل 1]: تحويله لمفتاح أجنبي مرتبط بجدول العملات
            $table->foreignId('currency_id')
                  ->nullable()
                  ->constrained('currencies')
                  ->nullOnDelete();

            $table->boolean('is_transactional')->default(true);
            $table->boolean('requires_cost_center')->default(false);
            $table->boolean('is_active')->default(true);

            // [تعديل 2]: تغيير الاسم ليتطابق مع الموديل والسيرفس
            $table->string('description')->nullable();

            // NestedSet Columns (_lft, _rgt, parent_id)
            $table->nestedSet();
            $table->unsignedInteger('level')->default(1)->comment('مستوى الحساب في الشجرة');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
