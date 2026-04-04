<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();

            // 1. التبعية (لمن هذا الترقيم؟)
            // نستخدم اسم الكلاس كاملاً (مثلاً: App\Modules\Accounting\Models\JournalEntry)
            $table->string('model', 150)->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index(); // لدعم تعدد الفروع

            // 2. التكوين المرن (The Configuration)
            // الصيغة: يمكنك كتابة أي شكل هنا (مثال: JE-{Y}-{00000})
            $table->string('format')->default('{Y}-{00000}');

            // متى يتم تصفير العداد؟ (yearly, monthly, never)
            $table->string('reset_frequency')->default('yearly');

            // 3. حالة العداد (State)
            $table->unsignedBigInteger('next_value')->default(1); // الرقم القادم

            // نحتفظ بآخر سنة/شهر تم التوليد فيها لنعرف متى نصفر
            $table->integer('current_year')->nullable();
            $table->integer('current_month')->nullable();

            // 4. قيود (Constraints)
            // لا يجوز تكرار إعداد الترقيم لنفس الموديل في نفس الفرع
            $table->unique(['model', 'branch_id'], 'seq_model_branch_unique');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
