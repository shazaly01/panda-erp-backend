<?php

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
        Schema::create('inventory_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('inventory_warehouse_locations')->nullOnDelete()->comment('الرف أو الـ Bin الحركي الحالي');
            $table->foreignId('batch_id')->nullable()->constrained('inventory_stock_batches')->nullOnDelete();
            $table->string('serial_number');
            $table->string('status', 30)->default('in_stock')->comment('in_stock, dispatched, in_maintenance, scrapped, returned');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_id', 'serial_number'], 'inv_serials_prod_sn_unique');
            $table->index(['warehouse_id', 'status'], 'inv_serials_wh_stat_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_serials');
    }
};