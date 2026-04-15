<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CoreConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $sequences = [
            // ==========================================
            // 1. الوحدة المالية (Accounting Module)
            // ==========================================
            [
                'model' => 'App\Modules\Accounting\Models\JournalEntry',
                'branch_id' => null,
                'format' => 'JE-{Y}-{00000}', // JE-2026-00001
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'RECEIPT', // سند قبض
                'branch_id' => null,
                'format' => 'REC-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'PAYMENT', // سند صرف
                'branch_id' => null,
                'format' => 'PAY-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],

            // ==========================================
            // 2. الموارد البشرية (HR Module) - [مكتملة الأركان]
            // ==========================================
            [
                'model' => 'App\Modules\HR\Models\Employee', // أرقام الموظفين
                'branch_id' => null,
                'format' => '9000{00000}', // EMP-0001 (لا يصفر سنوياً)
                'reset_frequency' => 'never',
                'next_value' => 1,
                'current_year' => null,
                'current_month' => null,
            ],
            // 🚀 الإضافة الجديدة: ترقيم العقود
            [
                'model' => 'App\Modules\HR\Models\Contract',
                'branch_id' => null,
                'format' => 'CONT-{Y}-{0000}', // CONT-2026-0001
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            // 🚀 الإضافة الجديدة: ترقيم مسيرات الرواتب
            [
                'model' => 'App\Modules\HR\Models\PayrollBatch',
                'branch_id' => null,
                'format' => 'PB-{Y}-{0000}', // PB-2026-0001 (مسير راتب)
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
        ];

        foreach ($sequences as $seq) {
            DB::table('sequences')->updateOrInsert(
                [
                    'model' => $seq['model'],
                    'branch_id' => $seq['branch_id']
                ],
                [
                    'format' => $seq['format'],
                    'reset_frequency' => $seq['reset_frequency'],
                    'next_value' => DB::raw("IFNULL(next_value, {$seq['next_value']})"),
                    'current_year' => $seq['current_year'],
                    'current_month' => $seq['current_month'],
                    'updated_at' => $now,
                ]
            );
        }
    }
}
