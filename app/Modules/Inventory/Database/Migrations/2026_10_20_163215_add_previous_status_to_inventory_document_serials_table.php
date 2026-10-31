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
        Schema::table('inventory_document_serials', function (Blueprint $table): void {
            $table->string('previous_status', 30)
                ->nullable()
                ->after('replaced_serial_id')
                ->comment('حالة الأسطوانة السابقة قبل تأكيد هذا السند لإتاحة العكس والتعديل المرن');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_document_serials', function (Blueprint $table): void {
            $table->dropColumn('previous_status');
        });
    }
};