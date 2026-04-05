<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('leave_type_id')->constrained('hr_leave_types');

            $table->year('year'); // السنة المالية للرصيد
            $table->decimal('total_allocated', 8, 2)->default(0); // إجمالي المستحق
            $table->decimal('used_days', 8, 2)->default(0); // الأيام المستخدمة
            $table->decimal('balance', 8, 2)->default(0); // الرصيد المتبقي

            $table->timestamps();
            $table->softDeletes();

            // الموظف له رصيد واحد لكل نوع إجازة في السنة الواحدة
            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_balances');
    }
};
