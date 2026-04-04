<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name', 100);      // اسم البنك (الراجحي، الأهلي)
            $table->string('account_name', 100);   // وصف الحساب (جاري - فرع جدة)
            $table->string('account_number', 50);  // رقم الحساب
            $table->string('iban', 50)->nullable(); // الآيبان

            // الربط المحاسبي
            // هذا الحقل سيحمل ID الحساب المتفرع من (نقدية بالبنوك)
            $table->foreignId('account_id')
                  ->constrained('accounts')
                  ->restrictOnDelete();

            // عملة الحساب البنكي (لا يمكن تغييرها عادة)
            $table->foreignId('currency_id')->constrained('currencies');

            $table->foreignId('branch_id')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
