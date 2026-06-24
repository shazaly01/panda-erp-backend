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
        Schema::table('employees', function (Blueprint $table) {
            $table->date('internship_start_date')->nullable()->after('user_id')->comment('تاريخ بدء التدريب الفعلي في المنشأة');
            $table->date('internship_end_date')->nullable()->after('internship_start_date')->comment('التاريخ المتوقع لانتهاء فترة التدريب');
            $table->string('internship_status')->nullable()->after('internship_end_date')->comment('حالة التدريب التاريخية: active, completed, converted, terminated');
            $table->string('academic_institution')->nullable()->after('internship_status')->comment('الجهة الأكاديمية أو الجامعة التابع لها المتدرب');
            $table->string('academic_major')->nullable()->after('academic_institution')->comment('التخصص الدراسي للمتدرب');
            $table->integer('required_training_hours')->nullable()->after('academic_major')->comment('عدد ساعات التدريب المطلوبة من الجامعة إن وجدت');
            $table->text('internship_notes')->nullable()->after('required_training_hours')->comment('ملاحظات عامة خاصة بفترة تدريب المتدرب');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'internship_start_date',
                'internship_end_date',
                'internship_status',
                'academic_institution',
                'academic_major',
                'required_training_hours',
                'internship_notes'
            ]);
        });
    }
};
