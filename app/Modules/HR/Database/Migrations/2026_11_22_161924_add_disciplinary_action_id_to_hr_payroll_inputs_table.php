<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('hr_payroll_inputs', 'disciplinary_action_id')) {
            Schema::table('hr_payroll_inputs', function (Blueprint $table): void {
                $table->unsignedBigInteger('disciplinary_action_id')
                    ->nullable()
                    ->after('payroll_batch_id')
                    ->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('hr_payroll_inputs', 'disciplinary_action_id')) {
            Schema::table('hr_payroll_inputs', function (Blueprint $table): void {
                $table->dropIndex(['disciplinary_action_id']);
                $table->dropColumn('disciplinary_action_id');
            });
        }
    }
};