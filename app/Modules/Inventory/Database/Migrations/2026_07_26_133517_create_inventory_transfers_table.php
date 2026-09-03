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
        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->foreignId('from_warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->date('transfer_date');
            $table->string('status', 30)->default('pending')->comment('draft, pending, completed, cancelled');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['from_warehouse_id', 'to_warehouse_id'], 'inv_tr_wh_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transfers');
    }
};