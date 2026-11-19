<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            if (!Schema::hasColumn('partners', 'price_list_id')) {
                $table->foreignId('price_list_id')
                    ->nullable()
                    ->after('currency_id')
                    ->constrained('inventory_price_lists')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            if (Schema::hasColumn('partners', 'price_list_id')) {
                $table->dropForeign(['price_list_id']);
                $table->dropColumn('price_list_id');
            }
        });
    }
};