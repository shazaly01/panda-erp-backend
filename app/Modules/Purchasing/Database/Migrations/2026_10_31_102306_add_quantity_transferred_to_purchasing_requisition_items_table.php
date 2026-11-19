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
        Schema::table('purchasing_requisition_items', function (Blueprint $table): void {
            $table->decimal('quantity_transferred', 15, 4)
                ->default(0.0000)
                ->after('quantity_issued')
                ->comment('الكمية المحولة مخزنياً للوفاء بهذا البند');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchasing_requisition_items', function (Blueprint $table): void {
            $table->dropColumn('quantity_transferred');
        });
    }
};