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
            // 1. الوحدة المالية (Accounting / Finance)
            // ==========================================
            [
                'model' => 'acc_journal_entry', // قيد يومية
                'branch_id' => null,
                'format' => 'JE-{Y}-{00000}', // مثال: JE-2026-00001
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'acc_receipt', // سند قبض
                'branch_id' => null,
                'format' => 'REC-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'acc_payment', // سند صرف
                'branch_id' => null,
                'format' => 'PAY-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],

            // ==========================================
            // 2. الموارد البشرية (HR Module)
            // ==========================================
            [
                'model' => 'hr_employee', // أرقام الموظفين
                'branch_id' => null,
                'format' => '9000{00000}', // مثال: 900000001 (رقم وظيفي ثابت لا يتصفر)
                'reset_frequency' => 'never',
                'next_value' => 1,
                'current_year' => null,
                'current_month' => null,
            ],
            [
                'model' => 'hr_contract', // عقود العمل
                'branch_id' => null,
                'format' => 'CONT-{Y}-{0000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'hr_payroll_batch', // مسيرات الرواتب
                'branch_id' => null,
                'format' => 'PB-{Y}-{0000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],

            // ==========================================
            // 3. المبيعات (Sales Module)
            // ==========================================
            [
                'model' => 'sales_quotation', // عرض سعر
                'branch_id' => null,
                'format' => 'SQ-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'sales_order', // أمر بيع
                'branch_id' => null,
                'format' => 'SO-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'sales_invoice', // فاتورة مبيعات عميل
                'branch_id' => null,
                'format' => 'INV-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'sales_return', // إشعار دائن (مرتجع مبيعات)
                'branch_id' => null,
                'format' => 'CN-{Y}-{00000}', // Credit Note
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],

            // ==========================================
            // 4. المشتريات (Purchases Module)
            // ==========================================
            [
                'model' => 'pur_order', // أمر شراء
                'branch_id' => null,
                'format' => 'PO-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'pur_bill', // فاتورة مشتريات مورد
                'branch_id' => null,
                'format' => 'BILL-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'pur_return', // إشعار مدين (مرتجع مشتريات)
                'branch_id' => null,
                'format' => 'DN-{Y}-{00000}', // Debit Note
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],

            // ==========================================
            // 5. المخزون (Inventory) - [حركات كثيفة = تصفير شهري]
            // ==========================================
            [
                'model' => 'inv_receipt', // سند استلام بضاعة
                'branch_id' => null,
                'format' => 'IN-{YM}-{0000}', // مثال: IN-2604-0001 (سنة 26 شهر 04)
                'reset_frequency' => 'monthly', // تصفير شهري لأن الحركات كثيرة
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'inv_delivery', // سند صرف بضاعة / تسليم
                'branch_id' => null,
                'format' => 'OUT-{YM}-{0000}',
                'reset_frequency' => 'monthly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'inv_transfer', // تحويل مخزني داخلي
                'branch_id' => null,
                'format' => 'TR-{YM}-{0000}',
                'reset_frequency' => 'monthly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],

            // ==========================================
            // 6. نقاط البيع (POS) - [حركات كثيفة جداً = تصفير شهري]
            // ==========================================
            [
                'model' => 'pos_receipt', // فاتورة كاشير
                'branch_id' => null,
                'format' => 'POS-{YM}-{00000}', // مثال: POS-2604-00001
                'reset_frequency' => 'monthly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
        ];

       foreach ($sequences as $seq) {
            // 🌟 الحل الأمثل: نتحقق أولاً إذا كان السجل موجوداً
            $exists = DB::table('sequences')
                ->where('model', $seq['model'])
                ->where('branch_id', $seq['branch_id'])
                ->exists();

            if (!$exists) {
                // إذا كان الجدول فارغاً (بعد fresh)، نقوم بإدخال نظيف
                DB::table('sequences')->insert(array_merge($seq, ['created_at' => $now, 'updated_at' => $now]));
            } else {
                // إذا كان السجل موجوداً، نحدث فقط الحقول الأساسية دون لمس الـ next_value الحالي
                DB::table('sequences')
                    ->where('model', $seq['model'])
                    ->where('branch_id', $seq['branch_id'])
                    ->update([
                        'format' => $seq['format'],
                        'reset_frequency' => $seq['reset_frequency'],
                        'updated_at' => $now,
                    ]);
            }
        }

        $this->command->info('تم تهيئة تسلسلات الترقيم (Panda ERP Standards) بنجاح.');
    }
}

