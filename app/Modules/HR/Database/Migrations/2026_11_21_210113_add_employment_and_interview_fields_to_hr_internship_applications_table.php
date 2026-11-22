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
        Schema::table('hr_internship_applications', function (Blueprint $table) {
            // التمييز بين طلبات التدريب وطلبات التوظيف، ومصدر الطلب (أونلاين / إدخال يدوي)
            $table->string('application_type', 30)
                ->default('internship')
                ->index()
                ->after('id')
                ->comment('نوع الطلب: internship (تدريب) أو employment (توظيف)');

            $table->string('source', 30)
                ->default('online')
                ->index()
                ->after('application_type')
                ->comment('مصدر الطلب: online (تقديم إلكتروني) أو manual (إدخال يدوي محلي)');

            // جعل حقول التدريب الأكاديمي والصورة اختيارية لدعم طلبات التوظيف والإدخال اليدوي
            $table->string('academic_institution')->nullable()->change();
            $table->string('academic_major')->nullable()->change();
            $table->string('photo_path')->nullable()->change();

            // حقول التوظيف الاختيارية
            $table->foreignId('position_id')
                ->nullable()
                ->after('required_training_hours')
                ->constrained('positions')
                ->nullOnDelete()
                ->comment('المسمى الوظيفي المستهدف في حال كان الطلب توظيفاً');

            $table->string('qualification')
                ->nullable()
                ->after('position_id')
                ->comment('المؤهل العلمي للمتقدم');

            $table->unsignedTinyInteger('experience_years')
                ->nullable()
                ->after('qualification')
                ->comment('عدد سنوات الخبرة');

            $table->string('cv_path')
                ->nullable()
                ->after('photo_path')
                ->comment('مسار ملف السيرة الذاتية المرفق');

            // حقول مرحلة المعاينة قبل القبول لفترة التدريب أو الرفض
            $table->dateTime('interview_date')
                ->nullable()
                ->after('approved_barcode')
                ->comment('موعد المعاينة المحدد للمتقدم');

            $table->text('interview_notes')
                ->nullable()
                ->after('interview_date')
                ->comment('ملاحظات وتقييم لجنة المعاينة');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_internship_applications', function (Blueprint $table) {
            $table->dropForeign(['position_id']);

            $table->dropColumn([
                'application_type',
                'source',
                'position_id',
                'qualification',
                'experience_years',
                'cv_path',
                'interview_date',
                'interview_notes',
            ]);
        });
    }
};