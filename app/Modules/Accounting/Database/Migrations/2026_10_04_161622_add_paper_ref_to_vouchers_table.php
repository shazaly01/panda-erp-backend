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
        Schema::table('vouchers', function (Blueprint $table) {
            // حقل اختياري لتسجيل رقم السند الورقي أو الدفتر القديم (مع فهرس للبحث السريع)
            $table->string('paper_ref')->nullable()->after('number')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex(['paper_ref']);
            $table->dropColumn('paper_ref');
        });
    }
};