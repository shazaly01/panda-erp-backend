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
        Schema::create('inventory_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('inventory_warehouse_locations')->nullOnDelete()->comment('موقع التخزين/الرف الذي جرت عليه الحركة');
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('product_unit_id')->constrained('inventory_product_units')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_stock_batches')->nullOnDelete();
            $table->foreignId('serial_id')->nullable()->constrained('inventory_serials')->nullOnDelete();
            
            $table->string('movement_type', 40)->comment('in, out, transfer_in, transfer_out, adjustment_in, adjustment_out, production_in, production_out');
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4)->default(0.0000)->comment('تكلفة الوحدة الواحدة في الحركة وقت التنفيذ');
            $table->decimal('total_cost', 15, 4)->default(0.0000)->comment('إجمالي تكلفة الحركة');
            $table->decimal('balance_after_movement', 15, 4)->default(0.0000)->comment('الرصيد التراكمي للصنف في المخزن فور انتهاء الحركة مباشرة');
            
            // مرجع المستند المتسبب بالحركة (أمر مبيعات، إذن استلام، أمر إنتاج... إلخ)
            $table->nullableMorphs('reference');
            
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'product_id'], 'inv_mvmt_wh_prod_idx');
            $table->index(['movement_type', 'created_at'], 'inv_mvmt_type_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_movements');
    }
};