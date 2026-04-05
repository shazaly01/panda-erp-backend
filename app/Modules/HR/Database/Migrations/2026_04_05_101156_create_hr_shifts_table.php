<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // مثال: وردية صباحية
            $table->time('start_time'); // وقت بداية الدوام
            $table->time('end_time'); // وقت نهاية الدوام
            $table->integer('grace_period_minutes')->default(15); // فترة السماح للتأخير بالدقائق
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes(); // دعم الحذف الناعم
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_shifts');
    }
};
