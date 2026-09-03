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
        Schema::create('inventory_warehouse_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('inventory_warehouse_locations')->nullOnDelete();
            $table->string('name');
            $table->string('code', 50)->nullable()->comment('كود الممر أو الرف أو الصندوق مثل: A1-R2-B3');
            $table->string('type', 30)->default('bin')->comment('aisle, rack, shelf, bin');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['warehouse_id', 'code']);
            $table->index(['warehouse_id', 'parent_id']);
            $table->index(['warehouse_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_warehouse_locations');
    }
};