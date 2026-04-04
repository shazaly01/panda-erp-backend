<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_details', function (Blueprint $table) {
            $table->id();

            // 1. الربط برأس القيد (Cascade Delete: إذا حذفنا المسودة، تحذف تفاصيلها)
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();

            // 2. الحساب ومركز التكلفة
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->restrictOnDelete();

            // 3. الأطراف (Sub-Ledger)
            // هذا يسمح بربط السطر بـ (عميل، مورد، موظف)
            // سينشئ عمودين: party_type (String), party_id (BigInt)
            // بدلاً من $table->nullableMorphs('party');
            $table->string('party_type')->nullable();
            // استخدام DECIMAL(18,0) ليتوافق مع أرقام الموظفين والعملاء في نظامك
            $table->decimal('party_id', 18, 0)->nullable();

            $table->index(['party_type', 'party_id']); // فهرس لسرعة البحث

            // 4. المبالغ (دقة عالية جداً)
            $table->decimal('debit', 18, 4)->default(0);
            $table->decimal('credit', 18, 4)->default(0);

            // 5. شرح إضافي للسطر
            $table->string('description', 255)->nullable();

            $table->timestamps();

            // فهرس لتحسين سرعة البحث في كشف الحساب
            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_details');
    }
};
