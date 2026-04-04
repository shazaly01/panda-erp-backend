<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150); // اسم الوظيفة: مهندس برمجيات
            $table->string('code', 50)->nullable();

            // الوصف الوظيفي (Job Description)
            // مفيد جداً في التوظيف وتقييم الأداء
            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
