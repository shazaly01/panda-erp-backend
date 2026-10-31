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
        Schema::table('inventory_serials', function (Blueprint $table) {
            $table->string('status', 30)
                ->default('in_stock')
                ->comment('حالات الأسطوانة: in_stock, in_service, empty, with_vendor, in_maintenance, replaced, scrapped')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_serials', function (Blueprint $table) {
            $table->string('status', 30)
                ->default('in_stock')
                ->comment('in_stock, dispatched, in_maintenance, scrapped, returned')
                ->change();
        });
    }
};