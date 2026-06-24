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
        Schema::create('hr_internship_applications', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->string('national_id')->nullable();
            $table->string('academic_institution');
            $table->string('academic_major');
            $table->integer('required_training_hours')->nullable();
            $table->date('internship_start_date');
            $table->date('internship_end_date');
            $table->string('photo_path')->comment('مسار الصورة الشخصية الحية الملتقطة من الكاميرا');
            $table->string('status')->default('pending')->comment('pending, approved, rejected');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_internship_applications');
    }
};
