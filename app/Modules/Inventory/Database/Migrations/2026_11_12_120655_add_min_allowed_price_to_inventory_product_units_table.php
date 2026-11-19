<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_product_units', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_product_units', 'min_allowed_price')) {
                $table->decimal('min_allowed_price', 15, 4)
                    ->default(0)
                    ->after('conversion_factor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_product_units', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_product_units', 'min_allowed_price')) {
                $table->dropColumn('min_allowed_price');
            }
        });
    }
};