<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('default_cost_center_id')->nullable()->after('password');
            $table->unsignedBigInteger('default_box_id')->nullable()->after('default_cost_center_id');
            $table->unsignedBigInteger('default_bank_account_id')->nullable()->after('default_box_id'); // 👈 البنك الافتراضي
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['default_cost_center_id', 'default_box_id', 'default_bank_account_id']);
        });
    }
};
