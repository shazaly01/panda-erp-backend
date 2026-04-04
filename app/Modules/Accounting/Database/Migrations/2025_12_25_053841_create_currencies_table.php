<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique(); // USD, SAR, SDG
            $table->string('name', 50);          // US Dollar
            $table->string('symbol', 10);        // $

            // سعر الصرف مقابل العملة الأساسية (العملة الأساسية تكون 1.000000)
            $table->decimal('exchange_rate', 18, 6)->default(1);

            $table->boolean('is_base')->default(false); // هل هي عملة النظام الأساسية؟
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
