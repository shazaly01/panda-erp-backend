<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الهجرة لإضافة حقول مرونة احتساب العمل الإضافي والضريبة.
     */
    public function up(): void
    {
        Schema::table('hr_overtime_policies', function (Blueprint $table) {
            $table->decimal('wage_factor', 5, 4)
                ->default(1.0000)
                ->after('is_daily_basis')
                ->comment('نسبة وعاء الراتب لاحتساب أجر الساعة (الافتراضي 1.0000 أي 100%)');

            $table->unsignedSmallInteger('fixed_monthly_hours')
                ->nullable()
                ->after('wage_factor')
                ->comment('ساعات العمل الشهرية الثابتة إن وُجدت (مثل 176 ساعة)، وإذا تُرِكت فارغة تُحسب آلياً');

            $table->decimal('tax_rate', 5, 2)
                ->default(0.00)
                ->after('fixed_monthly_hours')
                ->comment('نسبة ضريبة العمل الإضافي المنفصلة (الافتراضي 0.00%)');

            $table->json('tax_exempt_employment_types')
                ->nullable()
                ->after('tax_rate')
                ->comment('أنواع التوظيف المعفاة من استقطاع ضريبة الإضافي (JSON Array)');
        });
    }

    /**
     * التراجع عن الهجرة وحذف الحقول المضافة.
     */
    public function down(): void
    {
        Schema::table('hr_overtime_policies', function (Blueprint $table) {
            $table->dropColumn([
                'wage_factor',
                'fixed_monthly_hours',
                'tax_rate',
                'tax_exempt_employment_types',
            ]);
        });
    }
};