<?php

declare(strict_types=1);

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
            [
                'model' => 'acc_cash_transfer', // تحويل نقدية بين الخزائن والبنوك
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
                'model' => 'crm_customer', // كود العميل
                'branch_id' => null,
                'format' => 'CUST-{00000}', // تسلسلي دائم لا يتصفر
                'reset_frequency' => 'never',
                'next_value' => 1,
                'current_year' => null,
                'current_month' => null,
            ],
            [
                'model' => 'crm_vendor', // كود المورد
                'branch_id' => null,
                'format' => 'VEND-{00000}', // تسلسلي دائم لا يتصفر
                'reset_frequency' => 'never',
                'next_value' => 1,
                'current_year' => null,
                'current_month' => null,
            ],

            // ==========================================
            // 3. الموارد البشرية (HR Module)
            // ==========================================
            [
                'model' => 'hr_employee', // أرقام الموظفين الوظيفية
                'branch_id' => null,
                'format' => 'EMP-{00000}',
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
            // 4. المبيعات (Sales Module)
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
            // 5. المشتريات (Purchases Module)
            // ==========================================
            [
                'model' => 'pur_requisition', // طلب شراء داخلي من الأقسام
                'branch_id' => null,
                'format' => 'PR-{Y}-{00000}',
                'reset_frequency' => 'yearly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'pur_order', // أمر شراء معتمد
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
            // 6. المخزون (Inventory)
            // ==========================================
            [
                'model' => 'inv_receipt', // سند استلام بضاعة مخزني
                'branch_id' => null,
                'format' => 'IN-{YM}-{0000}',
                'reset_frequency' => 'monthly',
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
                'model' => 'inv_transfer', // تحويل مخزني بين المستودعات
                'branch_id' => null,
                'format' => 'TR-{YM}-{0000}',
                'reset_frequency' => 'monthly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'inv_adjustment', // محضر تسوية وجرد المخزون
                'branch_id' => null,
                'format' => 'ADJ-{YM}-{0000}',
                'reset_frequency' => 'monthly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'inv_scrap', // محضر إتلاف منتهي الصلاحية والهالك
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
                'model' => 'pos_receipt', // فاتورة بيع نقطة بيع
                'branch_id' => null,
                'format' => 'POS-{YM}-{00000}',
                'reset_frequency' => 'monthly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
            [
                'model' => 'pos_shift', // جلسة ووردية الكاشير
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
                'model' => 'ast_asset', // بطاقة تعريف الأصل الثابت
                'branch_id' => null,
                'format' => 'AST-{00000}',
                'reset_frequency' => 'never',
                'next_value' => 1,
                'current_year' => null,
                'current_month' => null,
            ],
            [
                'model' => 'ast_depreciation', // قيد إهلاك الأصول الدوري
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
                'model' => 'mfg_production_order', // أمر إنتاج / تشغيل مطبخ
                'branch_id' => null,
                'format' => 'MO-{YM}-{0000}',
                'reset_frequency' => 'monthly',
                'next_value' => 1,
                'current_year' => $now->year,
                'current_month' => $now->month,
            ],
        ];

        foreach ($sequences as $seq) {
            $exists = DB::table('sequences')
                ->where('model', $seq['model'])
                ->where('branch_id', $seq['branch_id'])
                ->exists();

            if (!$exists) {
                DB::table('sequences')->insert(array_merge($seq, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            } else {
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

        $this->command->info('تم تهيئة كافة تسلسلات الترقيم المعيارية لجميع الوحدات بنجاح.');
    }
}