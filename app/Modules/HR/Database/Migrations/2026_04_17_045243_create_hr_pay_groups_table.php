<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_pay_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // مثال: عمال المصنع، موظفي الإدارة
            $table->string('frequency'); // monthly, weekly, bi_weekly
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_pay_groups');
    }
};
