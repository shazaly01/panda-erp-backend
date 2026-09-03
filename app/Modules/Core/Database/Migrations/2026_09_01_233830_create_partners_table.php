<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            
            // الكود التسلسلي المولد آلياً
            $table->string('partner_code', 50)->unique()->index();
            
            // الهوية والاسم
            $table->string('type')->default('company');
            $table->string('name');
            $table->string('commercial_name')->nullable();
            
            // البيانات الرسمية والضريبية
            $table->string('tax_number', 50)->nullable()->index();
            $table->string('commercial_registry', 50)->nullable();
            $table->string('tax_treatment', 30)->default('taxable')->index();
            
            // الأدوار التشغيلية
            $table->boolean('is_customer')->default(false)->index();
            $table->boolean('is_supplier')->default(false)->index();
            
            // الحالة والسياسة الائتمانية
            $table->string('status')->default('active')->index();
            $table->decimal('credit_limit', 15, 4)->default(0.0000);
            $table->unsignedSmallInteger('credit_period_days')->default(0);
            
            // العملة الافتراضية وحسابات التوجيه المالي المخصصة (اختيارية لتجاوز الإعداد المركزي)
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('receivable_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('payable_account_id')->nullable()->constrained('accounts')->nullOnDelete();

            // البيانات المصرفية للشريك (لأوامر الصرف والتحويل البنكي)
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('iban', 50)->nullable();
            $table->string('swift_code', 20)->nullable();
            
            // التواصل والعنوان
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            
            // التدقيق والتتبع
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};