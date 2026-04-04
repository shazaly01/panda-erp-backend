<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_mappings', function (Blueprint $table) {
            $table->id();

            // 1. المفتاح البرمجي (الذي ستستخدمه في الكود)
            // أمثلة: sales_revenue, tax_vat, inventory_asset, cost_of_goods_sold
            $table->string('key', 100)->index();

            // 2. الحساب المرتبط
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();

            // 3. دعم الفروع (اختياري)
            // قد يكون حساب المبيعات في الرياض مختلفاً عن جدة
            $table->foreignId('branch_id')->nullable()->index();

            // وصف للمحاسب ليعرف ماذا يفعل هذا التوجيه
            $table->string('name')->nullable(); // مثال: حساب إيرادات المبيعات العامة

            // ضمان عدم تكرار المفتاح لنفس الفرع
            $table->unique(['key', 'branch_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_mappings');
    }
};
