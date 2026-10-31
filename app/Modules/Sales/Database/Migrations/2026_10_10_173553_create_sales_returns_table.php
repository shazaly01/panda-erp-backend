<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 50)->unique();
            $table->foreignId('sales_invoice_id')->nullable()->constrained('sales_invoices')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('pos_shifts')->cascadeOnUpdate()->nullOnDelete();
            
            $table->timestamp('return_date')->useCurrent()->index();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('exchange_rate', 10, 4)->default(1.0000);
            
            $table->string('status', 20)->default('draft')->index(); // draft, confirmed, cancelled
            $table->string('posting_status', 20)->default('unposted')->index(); // unposted, posted
            $table->timestamp('posted_at')->nullable();
            
            $table->string('refund_status', 20)->default('unpaid')->index(); // unpaid, partially_refunded, refunded
            
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('discount_amount', 15, 2)->default(0.00);
            $table->decimal('taxable_amount', 15, 2)->default(0.00);
            $table->decimal('vat_amount', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->decimal('refunded_amount', 15, 2)->default(0.00);
            $table->decimal('remaining_amount', 15, 2)->default(0.00);
            
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};