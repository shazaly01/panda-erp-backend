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
            // 1. الوحدة المالية (Accounting Module) - [مفعل حالياً]
            // ==========================================
            [
                'model' => 'App\Modules\Accounting\Models\JournalEntry',
                'branch_id' => null,
                'format' => 'JE-{Y}-{00000}', // JE-2025-00001
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'RECEIPT', // سند قبض
                'branch_id' => null,
                'format' => 'REC-{Y}-{00000}', // REC-2025-00001
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'PAYMENT', // سند صرف
                'branch_id' => null,
                'format' => 'PAY-{Y}-{00000}', // PAY-2025-00001
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],

            // ==========================================
            // 2. وحدة المبيعات (Sales Module) - [مستقبلي]
            // ==========================================
            /*
            [
                'model' => 'App\Modules\Sales\Models\Invoice', // فاتورة مبيعات
                'branch_id' => null,
                'format' => 'INV-{Y}/{00000}', // INV-2025/00001
                'reset_frequency' => 'yearly',
                'next_value' => 1,
            ],
            [
                'model' => 'App\Modules\Sales\Models\Quote', // عرض سعر
                'branch_id' => null,
                'format' => 'Q-{Y}-{0000}', // Q-2025-0001
                'reset_frequency' => 'yearly',
                'next_value' => 1,
            ],
            */

            // ==========================================
            // 3. وحدة المشتريات (Purchases Module) - [مستقبلي]
            // ==========================================
            /*
            [
                'model' => 'App\Modules\Purchases\Models\PurchaseOrder', // أمر شراء
                'branch_id' => null,
                'format' => 'PO-{Y}-{0000}', // PO-2025-0001
                'reset_frequency' => 'yearly',
                'next_value' => 1,
            ],
            [
                'model' => 'App\Modules\Purchases\Models\Bill', // فاتورة مورد
                'branch_id' => null,
                'format' => 'BILL-{Y}-{0000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
            ],
            */

            // ==========================================
            // 4. وحدة المخزون (Inventory Module) - [مستقبلي]
            // ==========================================
            /*
            [
                'model' => 'App\Modules\Inventory\Models\Transfer', // تحويل مخزني
                'branch_id' => null,
                'format' => 'TRNS-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
            ],
            [
                'model' => 'App\Modules\Inventory\Models\Adjustment', // تسوية جردية
                'branch_id' => null,
                'format' => 'ADJ-{Y}-{0000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
            ],
            */

            // ==========================================
            // 5. الموارد البشرية (HR Module) - [مستقبلي]
            // ==========================================
            /*
            [
                'model' => 'App\Modules\HR\Models\Employee', // أرقام الموظفين
                'branch_id' => null,
                'format' => 'EMP-{0000}', // EMP-0001 (لا يصفر سنوياً)
                'reset_frequency' => 'never',
                'next_value' => 1,
            ],
            */
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
                    'next_value' => DB::raw("IFNULL(next_value, {$seq['next_value']})"), // لا نعيد تصفير العداد إذا كان موجوداً مسبقاً
                    'current_year' => $seq['current_year'] ?? null,
                    'current_month' => $seq['current_month'] ?? null,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
