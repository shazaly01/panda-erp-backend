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
        Schema::table('purchasing_requisitions', function (Blueprint $table): void {
            $table->foreignId('received_by')
                ->nullable()
                ->after('requested_by')
                ->constrained('employees')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchasing_requisitions', function (Blueprint $table): void {
            $table->dropForeign(['received_by']);
            $table->dropColumn('received_by');
        });
    }
};