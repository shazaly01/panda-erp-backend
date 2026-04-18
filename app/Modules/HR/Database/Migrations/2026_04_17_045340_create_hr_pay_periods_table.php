<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_pay_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_group_id')->constrained('hr_pay_groups')->cascadeOnDelete();
            $table->string('name'); // مثال: يناير 2026، أو الأسبوع 14
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('open'); // open, processing, closed
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_pay_periods');
    }
};
