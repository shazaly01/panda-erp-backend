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
            $table->date('internship_start_date')->nullable()->change();
            $table->date('internship_end_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_internship_applications', function (Blueprint $table) {
            $table->date('internship_start_date')->nullable(false)->change();
            $table->date('internship_end_date')->nullable(false)->change();
        });
    }
};
