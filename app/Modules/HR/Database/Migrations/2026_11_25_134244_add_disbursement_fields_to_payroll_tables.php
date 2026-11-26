<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table): void {
            $table->decimal('disbursed_amount', 15, 2)
                ->default(0)
                ->after('status')
                ->comment('إجمالي المبلغ المستلم والمقرر صرفه فعلياً في هذا المسير');

            $table->decimal('disbursement_percentage', 5, 2)
                ->default(100.00)
                ->after('disbursed_amount')
                ->comment('نسبة الصرف المئوية المطبقة على رواتب الموظفين (من 0 إلى 100)');
        });

        Schema::table('payslips', function (Blueprint $table): void {
            $table->decimal('paid_amount', 15, 2)
                ->default(0)
                ->after('net_salary')
                ->comment('المبلغ المقرر صرفه للموظف من هذا المسير حسب نسبة السيولة المتاحة');

            $table->decimal('remaining_amount', 15, 2)
                ->default(0)
                ->after('paid_amount')
                ->comment('المبلغ المتبقي من راتب الموظف كمديونية في ذمة الشركة');
        });

        // تهيئة البيانات التاريخية السابقة باعتبارها مصروفة بالكامل (100%)
        DB::table('payslips')->update([
            'paid_amount'      => DB::raw('net_salary'),
            'remaining_amount' => 0,
        ]);

        DB::statement('
            UPDATE payroll_batches
            SET disbursed_amount = COALESCE((
                SELECT SUM(net_salary)
                FROM payslips
                WHERE payslips.payroll_batch_id = payroll_batches.id
            ), 0),
            disbursement_percentage = 100.00
        ');
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table): void {
            $table->dropColumn(['paid_amount', 'remaining_amount']);
        });

        Schema::table('payroll_batches', function (Blueprint $table): void {
            $table->dropColumn(['disbursed_amount', 'disbursement_percentage']);
        });
    }
};