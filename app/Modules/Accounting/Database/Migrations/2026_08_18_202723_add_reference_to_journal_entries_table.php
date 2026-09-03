<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الميجريشن
     */
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->nullableMorphs('reference');
        });
    }

    /**
     * التراجع عن الميجريشن
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropMorphs('reference');
        });
    }
};