<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_details', function (Blueprint $table) {
            $table->string('party_type')->nullable()->after('description');
            $table->string('party_id')->nullable()->after('party_type');
        });
    }

    public function down(): void
    {
        Schema::table('voucher_details', function (Blueprint $table) {
            $table->dropColumn(['party_type', 'party_id']);
        });
    }
};
