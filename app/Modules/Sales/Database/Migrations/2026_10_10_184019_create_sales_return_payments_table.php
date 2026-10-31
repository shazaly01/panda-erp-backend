<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_return_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 50)->unique();
            $table->foreignId('sales_return_id')->constrained('sales_returns')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('pos_shifts')->cascadeOnUpdate()->nullOnDelete();
            
            $table->string('payment_method', 30)->index(); // cash, card, bank_transfer, on_account
            $table->decimal('amount', 15, 2)->default(0.00);
            
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('exchange_rate', 10, 4)->default(1.0000);
            
            $table->foreignId('box_id')->nullable()->constrained('boxes')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->cascadeOnUpdate()->nullOnDelete();
            
            $table->string('transaction_reference', 100)->nullable();
            $table->timestamp('payment_date')->useCurrent()->index();
            
            $table->text('notes')->nullable();
            
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_payments');
    }
};