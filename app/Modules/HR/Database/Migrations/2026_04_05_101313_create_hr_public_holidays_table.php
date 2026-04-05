<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_public_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150); // مثال: عيد الفطر
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_paid')->default(true); // مدفوعة الأجر
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_public_holidays');
    }
};
