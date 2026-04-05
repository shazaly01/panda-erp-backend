<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique(); // مثل: ANNUAL, SICK
            $table->boolean('is_paid')->default(true); // هل هي مدفوعة الأجر أم تخصم من الراتب؟
            $table->decimal('max_days_per_year', 5, 2)->default(30); // أيام الإجازة السنوية (استخدمنا decimal لدعم 1.5 يوم مثلاً)
            $table->boolean('requires_approval')->default(true); // هل تتطلب موافقة الإدارة؟

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_types');
    }
};
