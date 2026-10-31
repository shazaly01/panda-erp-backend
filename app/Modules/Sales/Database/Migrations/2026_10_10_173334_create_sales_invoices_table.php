<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->string('reference_number', 100)->nullable()->index();
            $table->timestamp('invoice_date')->useCurrent()->index();
            $table->date('due_date')->nullable();
            
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('exchange_rate', 10, 4)->default(1.0000);
            
            $table->foreignId('shift_id')->nullable()->constrained('pos_shifts')->cascadeOnUpdate()->nullOnDelete();
            
            $table->string('channel', 20)->default('standard')->index(); // standard, pos
            $table->string('order_type', 30)->default('standard'); // standard, dine_in, takeaway, delivery
            $table->string('table_label', 50)->nullable();
            
            $table->string('status', 20)->default('draft')->index(); // draft, confirmed, cancelled
            $table->string('payment_status', 20)->default('unpaid')->index(); // unpaid, partially_paid, paid
            $table->string('posting_status', 20)->default('unposted')->index(); // unposted, posted
            $table->timestamp('posted_at')->nullable();
            
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->string('discount_type', 20)->default('fixed'); // fixed, percentage
            $table->decimal('discount_value', 15, 2)->default(0.00);
            $table->decimal('discount_amount', 15, 2)->default(0.00);
            $table->decimal('taxable_amount', 15, 2)->default(0.00);
            $table->decimal('vat_amount', 15, 2)->default(0.00);
            $table->decimal('service_charge', 15, 2)->default(0.00);
            $table->decimal('delivery_fee', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->decimal('paid_amount', 15, 2)->default(0.00);
            $table->decimal('remaining_amount', 15, 2)->default(0.00);
            
            $table->text('notes')->nullable();
            $table->text('terms_conditions')->nullable();
            
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoices');
    }
};