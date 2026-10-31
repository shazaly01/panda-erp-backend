<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class CoreConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $sequences = [
            // ==========================================
            // 1. الوحدة المالية (Accounting / Finance)
            // ==========================================
            [
                'model' => 'acc_journal_entry',
                'branch_id' => null,
                'format' => 'JE-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'acc_receipt',
                'branch_id' => null,
                'format' => 'REC-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'acc_payment',
                'branch_id' => null,
                'format' => 'PAY-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'acc_cash_transfer',
                'branch_id' => null,
                'format' => 'TRF-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],

            // ==========================================
            // 2. جهات التعامل والشركاء (Contacts / CRM)
            // ==========================================
            [
                'model' => 'partners',
                'branch_id' => null,
                'format' => '{PREFIX}-{00000}',
                'reset_frequency' => 'never',
                'next_value' => 1,
                'current_year' => null,
                'current_month' => null,
            ],

            // ==========================================
            // 3. الموارد البشرية (HR Module)
            // ==========================================
            [
                'model' => 'hr_employee',
                'branch_id' => null,
                'format' => 'EMP-{00000}',
                'reset_frequency' => 'never',
                'next_value' => 1,
                'current_year' => null,
                'current_month' => null,
            ],
            [
                'model' => 'hr_contract',
                'branch_id' => null,
                'format' => 'CONT-{Y}-{0000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'hr_payroll_batch',
                'branch_id' => null,
                'format' => 'PB-{Y}-{0000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],

            // ==========================================
            // 4. المبيعات (Sales Module)
            // ==========================================
            [
                'model' => 'sales_quotation',
                'branch_id' => null,
                'format' => 'SQ-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'sales_order',
                'branch_id' => null,
                'format' => 'SO-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'sales_invoice',
                'branch_id' => null,
                'format' => 'INV-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'sales_return',
                'branch_id' => null,
                'format' => 'CN-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],

            // ==========================================
            // 5. المشتريات (Purchases Module)
            // ==========================================
            [
                'model' => 'pur_requisition',
                'branch_id' => null,
                'format' => 'PR-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'pur_order',
                'branch_id' => null,
                'format' => 'PO-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'pur_bill',
                'branch_id' => null,
                'format' => 'BILL-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'pur_return',
                'branch_id' => null,
                'format' => 'DN-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],

            // ==========================================
            // 6. المخزون (Inventory)
            // ==========================================
            [
                'model' => 'inv_receipt',
                'branch_id' => null,
                'format' => 'IN-{YM}-{0000}',
                'reset_frequency' => 'monthly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'inv_delivery',
                'branch_id' => null,
                'format' => 'OUT-{YM}-{0000}',
                'reset_frequency' => 'monthly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'inv_transfer',
                'branch_id' => null,
                'format' => 'TR-{YM}-{0000}',
                'reset_frequency' => 'monthly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'inv_adjustment',
                'branch_id' => null,
                'format' => 'ADJ-{YM}-{0000}',
                'reset_frequency' => 'monthly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'inv_scrap',
                'branch_id' => null,
                'format' => 'SCRAP-{YM}-{0000}',
                'reset_frequency' => 'monthly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],

            // ==========================================
            // 7. نقاط البيع والورديات (POS & Shifts)
            // ==========================================
            [
                'model' => 'pos_receipt',
                'branch_id' => null,
                'format' => 'POS-{YM}-{00000}',
                'reset_frequency' => 'monthly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'pos_shift',
                'branch_id' => null,
                'format' => 'SHIFT-{YM}-{0000}',
                'reset_frequency' => 'monthly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],

            // ==========================================
            // 8. الأصول الثابتة (Fixed Assets)
            // ==========================================
            [
                'model' => 'ast_asset',
                'branch_id' => null,
                'format' => 'AST-{00000}',
                'reset_frequency' => 'never',
                'next_value' => 1,
                'current_year' => null,
                'current_month' => null,
            ],
            [
                'model' => 'ast_depreciation',
                'branch_id' => null,
                'format' => 'DEP-{Y}-{0000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],

            // ==========================================
            // 9. التصنيع وتجهيز الوجبات (Kitchen / Manufacturing)
            // ==========================================
            [
                'model' => 'mfg_production_order',
                'branch_id' => null,
                'format' => 'MO-{YM}-{0000}',
                'reset_frequency' => 'monthly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
        ];

        foreach ($sequences as $seq) {
            $existing = DB::table('sequences')
                ->where('model', $seq['model'])
                ->where('branch_id', $seq['branch_id'])
                ->first();

            // فحص الجداول الفعلية لحساب القيمة الشاغرة التالية تلقائياً
            $resolvedNextValue = $this->resolveNextValue($seq['model'], (int) $seq['next_value'], $now->year);

            if (! $existing) {
                $seq['next_value'] = $resolvedNextValue;
                DB::table('sequences')->insert(array_merge($seq, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            } else {
                $updateData = [
                    'format'          => $seq['format'],
                    'reset_frequency' => $seq['reset_frequency'],
                    'updated_at'      => $now,
                ];

                // مزامنة السنة والشهر إذا كانا فارغين في قاعدة البيانات لمنع تصفير العداد خطأً
                if ($existing->current_year === null && $seq['current_year'] !== null) {
                    $updateData['current_year'] = $seq['current_year'];
                    $updateData['current_month'] = $seq['current_month'];
                }

                // إذا كانت هناك سجلات حقيقية بقيم أعلى من العداد الحالي، نقوم بتقديم العداد تلقائياً
                if ($resolvedNextValue > (int) $existing->next_value) {
                    $updateData['next_value'] = $resolvedNextValue;
                }

                DB::table('sequences')
                    ->where('id', $existing->id)
                    ->update($updateData);
            }
        }

        $this->command->info('تم تهيئة ومزامنة كافة تسلسلات الترقيم المعيارية لجميع الوحدات بنجاح.');
    }

    /**
     * فحص ذكي للبيانات المسجلة مسبقاً لتحديد العداد الصحيح التالي ومنع خطأ Duplicate Entry
     */
    private function resolveNextValue(string $model, int $fallbackValue, int $currentYear): int
    {
        $tableMap = [
            'acc_journal_entry' => ['table' => 'journal_entries', 'column' => 'entry_number', 'prefix' => "JE-{$currentYear}-"],
            'partners'          => ['table' => 'partners',        'column' => 'partner_code', 'prefix' => 'BP-'],
            'pur_bill'          => ['table' => 'purchase_bills',  'column' => 'bill_number',  'prefix' => 'BILL-'],
        ];

        if (! isset($tableMap[$model])) {
            return $fallbackValue;
        }

        $config = $tableMap[$model];

        if (! Schema::hasTable($config['table'])) {
            return $fallbackValue;
        }

        $maxExisting = DB::table($config['table'])
            ->where($config['column'], 'like', $config['prefix'] . '%')
            ->selectRaw("MAX(CAST(SUBSTRING_INDEX({$config['column']}, '-', -1) AS UNSIGNED)) as max_val")
            ->value('max_val');

        return $maxExisting ? ((int) $maxExisting + 1) : $fallbackValue;
    }
}