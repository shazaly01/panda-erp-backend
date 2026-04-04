<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Modules\Accounting\Enums\VoucherType;
use App\Modules\Accounting\Enums\VoucherStatus;

return new class extends Migration
{
    public function up(): void
    {
        // 1. جدول رأس السند (Vouchers)
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();

            // الفرع (مركز التكلفة) - هام جداً للترقيم المنفصل
            $table->foreignId('branch_id')->constrained('cost_centers')->restrictOnDelete();

            // نوع السند (صرف / قبض) - نصي
            $table->string('type', 50)->index();

            // رقم السند المميز (RY-PAY-2025-001)
            $table->string('number', 100)->unique();

            $table->date('date')->index();
            $table->string('description')->nullable();

            // وسيلة الدفع (إما خزينة أو بنك)
            $table->foreignId('box_id')->nullable()->constrained('boxes');
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts');

            // العملة
            $table->foreignId('currency_id')->constrained('currencies');
            $table->decimal('exchange_rate', 12, 4)->default(1);

            // المبلغ الإجمالي للسند
            $table->decimal('amount', 20, 4);

            // الحالة: القيمة الافتراضية "مسودة"
            $table->string('status', 30)->default(VoucherStatus::Draft->value)->index();

            // التواقيع (Audit Trail)
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable(); // من قام بالترحيل
            $table->timestamp('posted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // 2. جدول تفاصيل السند (Voucher Details)
        Schema::create('voucher_details', function (Blueprint $table) {
            $table->id();

            // الربط مع الرأس وحذف التفاصيل تلقائياً عند حذف السند
            $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();

            // الطرف الآخر للقيد (مثلاً: حساب مصروف الكهرباء، أو حساب المورد)
            $table->foreignId('account_id')->constrained('accounts');

            // مركز التكلفة الخاص بالسطر (مثلاً: هذا المصروف يخص مشروع البرج)
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers');

            $table->decimal('amount', 20, 4);
            $table->string('description')->nullable(); // شرح السطر

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_details');
        Schema::dropIfExists('vouchers');
    }
};
