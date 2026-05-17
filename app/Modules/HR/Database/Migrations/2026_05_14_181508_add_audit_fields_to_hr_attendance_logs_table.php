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
        Schema::table('hr_attendance_logs', function (Blueprint $table) {
            $table->boolean('is_manual_override')->default(false)->after('status')->comment('هل تم تعديل/إدخال السجل يدوياً من المشرف؟');
            $table->decimal('approved_by', 18, 0)->nullable()->after('is_manual_override')->comment('رقم الموظف/المشرف الذي قام بالتعديل');
            $table->string('override_reason')->nullable()->after('approved_by')->comment('سبب التعديل اليدوي');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_attendance_logs', function (Blueprint $table) {
            $table->dropColumn(['is_manual_override', 'approved_by', 'override_reason']);
        });
    }
};
