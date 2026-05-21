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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            // الاسم التجاري أو الوصف للملف المرفوع
            $table->string('name', 255);

            // المسار الفعلي لتخزين الملف داخل القرص المعين
            $table->string('file_path', 500);

            // نوع القرص المستخدم (public للصور العادية، private للوثائق الحساسة والعقود)
            $table->string('disk', 50)->default('private');

            // تصنيف المستند الفعلي (عقد، شهادة، فاتورة، صورة شخصية، إلخ)
            $table->string('document_type', 100);

            // الخصائص التقنية للمستند (المعيار العالمي DMS)
            $table->string('file_size', 50)->nullable(); // حجم الملف مقروءاً أو بالبايت
            $table->string('mime_type', 150)->nullable(); // نوع الملف التقني (application/pdf)
            $table->string('extension', 20)->nullable(); // الامتداد (pdf, png)

            // إعداد العلاقة متعددة الأوجه للربط مع أي موديل في الـ ERP
            // تستخدم bigInteger لتتوافق مع معرفات الجداول الحالية بنسبة 100%
            $table->string('documentable_type', 255);
            $table->unsignedBigInteger('documentable_id');

            // الفهرسة لضمان سرعة الاستعلام والبحث في قواعد البيانات الضخمة
            $table->index(['documentable_type', 'documentable_id']);

            // تتبع التدقيق والأمان: معرف المستخدم الذي قام برفع المستند
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
