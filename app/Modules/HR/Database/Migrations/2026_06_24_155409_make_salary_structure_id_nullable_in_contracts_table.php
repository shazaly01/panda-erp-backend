<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // إسقاط قيد مفتاح الربط الأجنبي القديم أولاً لتجنب تعارض قاعدة البيانات أثناء التعديل
            $table->dropForeign(['salary_structure_id']);
        });

        Schema::table('contracts', function (Blueprint $table) {
            // تعديل العمود ليصبح nullable وإعادة بناء قيد الربط بشكل صحيح وسليم
            $table->foreignId('salary_structure_id')
                  ->nullable()
                  ->change()
                  ->constrained('salary_structures')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['salary_structure_id']);
        });

        Schema::table('contracts', function (Blueprint $table) {
            // إعادة العمود لحالته الأولى (إلزامي) في حال التراجع عن الهجرة
            $table->foreignId('salary_structure_id')
                  ->nullable(false)
                  ->change()
                  ->constrained('salary_structures');
        });
    }
};
