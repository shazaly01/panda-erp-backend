<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Modules\Accounting\Enums\BudgetStatus;
use App\Modules\Accounting\Enums\BudgetControlMode;
use App\Modules\Accounting\Enums\BudgetPeriodType;

return new class extends Migration
{
    /**
     * تشغيل الهجرة
     */
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            
            $table->string('period_type')->default(BudgetPeriodType::Monthly->value);
            $table->string('control_mode')->default(BudgetControlMode::Advisory->value);
            $table->string('status')->default(BudgetStatus::Draft->value);
            
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->text('notes')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'start_date', 'end_date']);
        });
    }

    /**
     * التراجع عن الهجرة
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};