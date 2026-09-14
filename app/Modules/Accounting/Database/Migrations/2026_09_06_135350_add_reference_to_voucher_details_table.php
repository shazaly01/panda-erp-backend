<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_details', function (Blueprint $table) {
            $table->string('reference_type')->nullable()->after('party_id');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');

            $table->index(['reference_type', 'reference_id'], 'voucher_details_reference_index');
        });
    }

    public function down(): void
    {
        Schema::table('voucher_details', function (Blueprint $table) {
            $table->dropIndex('voucher_details_reference_index');
            $table->dropColumn(['reference_type', 'reference_id']);
        });
    }
};