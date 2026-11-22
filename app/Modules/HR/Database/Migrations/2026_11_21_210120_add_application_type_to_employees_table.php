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
            $table->string('application_type', 30)
                ->default('internship')
                ->index()
                ->after('employment_type')
                ->comment('أصل السجل أثناء فترة التدريب: internship (متدرب أكاديمي) أو employment (مرشح توظيف تحت التدريب)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('application_type');
        });
    }
};