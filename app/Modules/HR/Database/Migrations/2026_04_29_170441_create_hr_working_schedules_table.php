<?php

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
        Schema::create('hr_working_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['fixed', 'rotating'])
                  ->default('fixed')
                  ->comment('fixed: Weekly standard schedule, rotating: Custom shift cycle');
            $table->integer('cycle_days')
                  ->default(7)
                  ->comment('Length of the cycle in days. Fixed is usually 7, rotating can be 14, 28, etc.');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_working_schedules');
    }
};
