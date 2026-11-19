<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'default_warehouse_id')) {
                $table->foreignId('default_warehouse_id')
                    ->nullable()
                    ->after('default_cost_center_id')
                    ->constrained('inventory_warehouses')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('users', 'stock_control')) {
                $table->boolean('stock_control')
                    ->default(true)
                    ->after('default_bank_account_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'default_warehouse_id')) {
                $table->dropForeign(['default_warehouse_id']);
                $table->dropColumn('default_warehouse_id');
            }

            if (Schema::hasColumn('users', 'stock_control')) {
                $table->dropColumn('stock_control');
            }
        });
    }
};