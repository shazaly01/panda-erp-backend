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
        Schema::table('inventory_products', function (Blueprint $table) {
            $table->string('tracking_type', 30)
                ->default('none')
                ->comment('سياسات التتبع: none, by_batch, by_serial, by_weight_serial')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_products', function (Blueprint $table) {
            $table->string('tracking_type', 20)
                ->default('none')
                ->comment('none, by_batch, by_serial')
                ->change();
        });
    }
};