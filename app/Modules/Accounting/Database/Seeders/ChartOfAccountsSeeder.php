<?php

namespace App\Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Accounting\Models\Account;
use Illuminate\Support\Facades\DB;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // الهيكل الشامل (المعيار الذهبي)
        $accounts = [
            // ==========================================
            // 1. الأصول (Assets) - طبيعتها: مدين (Debit)
            // ==========================================
            ['code' => '1', 'name' => 'الأصول', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => false, 'parent_code' => null],

            // 11. الأصول المتداولة
            ['code' => '11', 'name' => 'الأصول المتداولة', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => false, 'parent_code' => '1'],
            ['code' => '1101', 'name' => 'النقدية بالصناديق', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '11'],
            ['code' => '1102', 'name' => 'النقدية بالبنوك', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '11'],
            ['code' => '1103', 'name' => 'العملاء (ذمم مدينة)', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '11'],
            ['code' => '1104', 'name' => 'المخزون', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '11'],
            ['code' => '1105', 'name' => 'سلف وعهد الموظفين', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '11'], // مربوط بالـ HR
            ['code' => '1106', 'name' => 'ضريبة القيمة المضافة (مدخلات/مشتريات)', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '11'],

            // 12. الأصول غير المتداولة (الثابتة)
            ['code' => '12', 'name' => 'الأصول غير المتداولة', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => false, 'parent_code' => '1'],
            ['code' => '1201', 'name' => 'الآلات والمعدات', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '12'],
            ['code' => '1202', 'name' => 'مجمع إهلاك الآلات والمعدات', 'type' => 'asset', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '12'], // طبيعته دائن لأنه أصل عكسي

            // ==========================================
            // 2. الخصوم والالتزامات (Liabilities) - طبيعتها: دائن (Credit)
            // ==========================================
            ['code' => '2', 'name' => 'الخصوم والالتزامات', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => false, 'parent_code' => null],

            // 21. الخصوم المتداولة
            ['code' => '21', 'name' => 'الخصوم المتداولة', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => false, 'parent_code' => '2'],
            ['code' => '2101', 'name' => 'الموردين (ذمم دائنة)', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '21'],
            ['code' => '2102', 'name' => 'رواتب وأجور مستحقة الدفع', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '21'], // مربوط بالـ HR
            ['code' => '2103', 'name' => 'التأمينات الاجتماعية - ذمم الموظفين', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '21'], // مربوط بالـ HR
            ['code' => '2104', 'name' => 'التزامات التأمينات - حصة الشركة', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '21'], // مربوط بالـ HR
            ['code' => '2105', 'name' => 'ضريبة القيمة المضافة (مخرجات/مبيعات)', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '21'],

            // ==========================================
            // 3. حقوق الملكية (Equity) - طبيعتها: دائن (Credit)
            // ==========================================
            ['code' => '3', 'name' => 'حقوق الملكية', 'type' => 'equity', 'nature' => 'credit', 'is_transactional' => false, 'parent_code' => null],
            ['code' => '3101', 'name' => 'رأس المال', 'type' => 'equity', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '3'],
            ['code' => '3102', 'name' => 'الأرباح (الخسائر) المبقاة', 'type' => 'equity', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '3'],
            ['code' => '3103', 'name' => 'المسحوبات الشخصية', 'type' => 'equity', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '3'], // طبيعته مدين

            // ==========================================
            // 4. الإيرادات (Revenues) - طبيعتها: دائن (Credit)
            // ==========================================
            ['code' => '4', 'name' => 'الإيرادات', 'type' => 'revenue', 'nature' => 'credit', 'is_transactional' => false, 'parent_code' => null],
            ['code' => '4101', 'name' => 'إيرادات المبيعات', 'type' => 'revenue', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '4'],
            ['code' => '4102', 'name' => 'مردودات المبيعات', 'type' => 'revenue', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '4'], // طبيعته مدين
            ['code' => '4103', 'name' => 'إيرادات أخرى (جزاءات موظفين وغيرها)', 'type' => 'revenue', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '4'], // مربوط بالـ HR

            // ==========================================
            // 5. تكلفة المبيعات (COGS) - طبيعتها: مدين (Debit)
            // ==========================================
            ['code' => '5', 'name' => 'تكلفة المبيعات', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => false, 'parent_code' => null],
            ['code' => '5101', 'name' => 'تكلفة البضاعة المباعة', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '5'],
            ['code' => '5102', 'name' => 'تسويات الجرد والمخزون', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '5'],

            // ==========================================
            // 6. المصروفات (Expenses) - طبيعتها: مدين (Debit)
            // ==========================================
            ['code' => '6', 'name' => 'المصروفات', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => false, 'parent_code' => null],

            // 61. المصروفات الإدارية والعمومية (الرواتب وغيرها)
            ['code' => '61', 'name' => 'المصروفات العمومية والإدارية', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => false, 'parent_code' => '6'],
            ['code' => '6101', 'name' => 'مصروف الرواتب والأجور الأساسية', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '61'], // مربوط بالـ HR
            ['code' => '6102', 'name' => 'مصروف بدلات السكن', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '61'], // مربوط بالـ HR
            ['code' => '6103', 'name' => 'مصروف بدلات النقل', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '61'], // مربوط بالـ HR
            ['code' => '6104', 'name' => 'مصروف التأمينات (حصة الشركة)', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '61'], // مربوط بالـ HR
            ['code' => '6105', 'name' => 'مصروف الإيجارات', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '61'],
            ['code' => '6106', 'name' => 'مصروف الكهرباء والماء', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '61'],

            ['code' => '6108', 'name' => 'مصروف العمل الإضافي', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '61'],
        ];

        // خطوة ذكية لضمان إدخال الآباء أولاً لتجنب خطأ الفورين كي (Foreign Key)
        $insertedAccounts = [];

        foreach ($accounts as $data) {
            $parentId = null;
            if ($data['parent_code']) {
                $parentId = $insertedAccounts[$data['parent_code']] ?? Account::where('code', $data['parent_code'])->value('id');
            }

            // حساب المستوى
            $level = 1;
            if ($parentId) {
                $parentLevel = Account::where('id', $parentId)->value('level');
                $level = $parentLevel + 1;
            }

            $account = Account::updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'nature' => $data['nature'],
                    'parent_id' => $parentId,
                    'level' => $level,
                    'is_transactional' => $data['is_transactional'],
                    'is_active' => true,
                ]
            );

            // حفظ الـ ID في المصفوفة لسرعة الوصول له في الدورات القادمة
            $insertedAccounts[$data['code']] = $account->id;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
