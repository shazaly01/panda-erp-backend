<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('shift_number', 50)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->foreignId('warehouse_id')->nullable()->constrained('inventory_warehouses')->cascadeOnUpdate()->nullOnDelete();
            
            $table->string('status', 20)->default('open')->index(); // open, closed
            
            $table->decimal('opening_cash', 15, 2)->default(0.00);
            $table->decimal('closing_cash', 15, 2)->nullable();
            $table->decimal('actual_cash', 15, 2)->nullable();
            $table->decimal('difference', 15, 2)->default(0.00);
            
            $table->decimal('total_sales', 15, 2)->default(0.00);
            $table->decimal('total_returns', 15, 2)->default(0.00);
            $table->decimal('total_cash', 15, 2)->default(0.00);
            $table->decimal('total_card', 15, 2)->default(0.00);
            
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_shifts');
    }
};