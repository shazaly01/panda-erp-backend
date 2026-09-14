<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الهجرة.
     */
    public function up(): void
    {
        Schema::table('purchasing_requisition_items', function (Blueprint $table): void {
            $table->decimal('quantity_issued', 15, 4)
                ->default(0.0000)
                ->after('quantity_ordered');
        });
    }

    /**
     * إلغاء الهجرة.
     */
    public function down(): void
    {
        Schema::table('purchasing_requisition_items', function (Blueprint $table): void {
            $table->dropColumn('quantity_issued');
        });
    }
};