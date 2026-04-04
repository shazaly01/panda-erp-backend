<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();

            // الكود والاسم
            $table->string('code', 50)->unique();
            $table->string('name', 150);

            // النوع: (Project, Department, Vehicle) - هام جداً للفلترة
            $table->string('type', 50)->index();

            // حالة التفعيل
            $table->boolean('is_active')->default(true);

            // ملاحظات
            $table->string('notes')->nullable();

            // الشجرة (Nested Sets) - لأن المشاريع قد تتفرع
            $table->nestedSet();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_centers');
    }
};
