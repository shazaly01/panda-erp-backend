<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الهجرة
     */
    public function up(): void
    {
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('planned_amount', 15, 4)->default(0);
            $table->text('notes')->nullable();
            
            $table->timestamps();

            $table->index(['budget_id', 'account_id', 'cost_center_id']);
            $table->index(['period_start', 'period_end']);
        });
    }

    /**
     * التراجع عن الهجرة
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
    }
};