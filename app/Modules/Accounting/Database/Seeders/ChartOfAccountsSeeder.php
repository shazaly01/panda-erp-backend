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

        // تفريغ الجدول لتجنب التكرار عند إعادة التشغيل
        Account::truncate();

        $accounts = [
            // ==========================================
            // 1. الأصول (Assets) - طبيعتها: مدين (Debit)
            // ==========================================
            ['code' => '1', 'name' => 'الأصول', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => false, 'parent_code' => null],

            // 11. الأصول المتداولة
            ['code' => '11', 'name' => 'الأصول المتداولة', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => false, 'parent_code' => '1'],

            // 111. النقدية وما في حكمها
            ['code' => '111', 'name' => 'النقدية وما في حكمها', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => false, 'parent_code' => '11'],
            ['code' => '11101', 'name' => 'النقدية بالصناديق (رئيسي)', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '111'],
            ['code' => '11102', 'name' => 'النقدية بالبنوك (رئيسي)', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '111'],
            ['code' => '11103', 'name' => 'العهد النقدية', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '111'],
            ['code' => '11104', 'name' => 'وسيط بوابات الدفع (Stripe/Visa)', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '111'],
            ['code' => '11105', 'name' => 'تسويات نقدية نقاط البيع (POS)', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '111'],

            // 112. الذمم المدينة (العملاء والمستحقات)
            ['code' => '112', 'name' => 'العملاء والذمم المدينة', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => false, 'parent_code' => '11'],
            ['code' => '11201', 'name' => 'العملاء (ذمم مدينة تجارية)', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '112'],
            ['code' => '11202', 'name' => 'إيرادات مستحقة غير مفوترة', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '112'],
            ['code' => '11203', 'name' => 'محتجزات ضمان لدى العملاء', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '112'],
            ['code' => '11204', 'name' => 'حساب جاري الفروع والشركات الشقيقة', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '112'],

            // 113. المخزون والأعمال تحت التنفيذ
            ['code' => '113', 'name' => 'المخزون', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => false, 'parent_code' => '11'],
            ['code' => '11301', 'name' => 'حساب المخزون (أصول)', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '113'],
            ['code' => '11302', 'name' => 'التكاليف المحملة (Landed Costs Clearing)', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '113'],
            ['code' => '11303', 'name' => 'أعمال تحت التنفيذ (مشاريع - WIP)', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '113'],
            ['code' => '11304', 'name' => 'مخزون المواد الخام (تصنيع)', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '113'],
            ['code' => '11305', 'name' => 'مخزون إنتاج تحت التشغيل (تصنيع)', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '113'],
            ['code' => '11306', 'name' => 'مخزون الإنتاج التام (تصنيع)', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '113'],

            // 114. أصول متداولة أخرى (مقدمات وضرائب وسلف)
            ['code' => '114', 'name' => 'أصول متداولة أخرى', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => false, 'parent_code' => '11'],
            ['code' => '11401', 'name' => 'دفعات مقدمة للموردين', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '114'],
            ['code' => '11402', 'name' => 'ضريبة القيمة المضافة (مدخلات/مشتريات)', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '114'],
            ['code' => '11403', 'name' => 'سلف وعهد الموظفين', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '114'],
            ['code' => '11404', 'name' => 'مصروفات مدفوعة مقدماً', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '114'],

            // 12. الأصول غير المتداولة (الثابتة)
            ['code' => '12', 'name' => 'الأصول غير المتداولة', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => false, 'parent_code' => '1'],
            ['code' => '12101', 'name' => 'حساب الأصول الثابتة (الوسيط تحت التجهيز)', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '12'],
            ['code' => '12102', 'name' => 'الآلات والمعدات', 'type' => 'asset', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '12'],
            ['code' => '12103', 'name' => 'مجمع إهلاك الآلات والمعدات', 'type' => 'asset', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '12'], // طبيعة عكسية (دائن)

            // ==========================================
            // 2. الخصوم والالتزامات (Liabilities) - طبيعتها: دائن (Credit)
            // ==========================================
            ['code' => '2', 'name' => 'الخصوم والالتزامات', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => false, 'parent_code' => null],

            // 21. الخصوم المتداولة
            ['code' => '21', 'name' => 'الخصوم المتداولة', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => false, 'parent_code' => '2'],

            // 211. الموردين
            ['code' => '211', 'name' => 'الموردين والذمم الدائنة', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => false, 'parent_code' => '21'],
            ['code' => '21101', 'name' => 'الموردين (ذمم دائنة تجارية)', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '211'],
            ['code' => '21102', 'name' => 'بضاعة مستلمة غير مفوترة (GRNI)', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '211'],

            // 212. الضرائب المستحقة
            ['code' => '212', 'name' => 'الضرائب والرسوم المستحقة', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => false, 'parent_code' => '21'],
            ['code' => '21201', 'name' => 'ضريبة القيمة المضافة (مخرجات/مبيعات)', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '212'],
            ['code' => '21202', 'name' => 'ضريبة الخصم والإضافة', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '212'],

            // 213. مستحقات الموظفين (HR)
            ['code' => '213', 'name' => 'مستحقات الموظفين', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => false, 'parent_code' => '21'],
            ['code' => '21301', 'name' => 'رواتب وأجور مستحقة الدفع', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '213'],
            ['code' => '21302', 'name' => 'التأمينات الاجتماعية - ذمم (حصة الموظف)', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '213'],
            ['code' => '21303', 'name' => 'التزامات التأمينات (حصة الشركة)', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '213'],

            // 214. التزامات متداولة أخرى
            ['code' => '214', 'name' => 'التزامات متداولة أخرى', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => false, 'parent_code' => '21'],
            ['code' => '21401', 'name' => 'إيرادات مقدمة', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '214'],

            // 22. الخصوم غير المتداولة (طويلة الأجل)
            ['code' => '22', 'name' => 'الخصوم غير المتداولة', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => false, 'parent_code' => '2'],
            ['code' => '22101', 'name' => 'مخصص مكافأة نهاية الخدمة', 'type' => 'liability', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '22'],

            // ==========================================
            // 3. حقوق الملكية (Equity) - طبيعتها: دائن (Credit)
            // ==========================================
            ['code' => '3', 'name' => 'حقوق الملكية', 'type' => 'equity', 'nature' => 'credit', 'is_transactional' => false, 'parent_code' => null],
            ['code' => '3101', 'name' => 'رأس المال', 'type' => 'equity', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '3'],
            ['code' => '3102', 'name' => 'الأرباح (الخسائر) المبقاة', 'type' => 'equity', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '3'],
            ['code' => '3103', 'name' => 'أرباح/خسائر العام الحالي', 'type' => 'equity', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '3'],
            ['code' => '3104', 'name' => 'المسحوبات الشخصية', 'type' => 'equity', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '3'], // طبيعة عكسية

            // ==========================================
            // 4. الإيرادات التشغيلية (Revenues) - طبيعتها: دائن (Credit)
            // ==========================================
            ['code' => '4', 'name' => 'الإيرادات التشغيلية', 'type' => 'revenue', 'nature' => 'credit', 'is_transactional' => false, 'parent_code' => null],
            ['code' => '4101', 'name' => 'إيرادات المبيعات الرئيسية', 'type' => 'revenue', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '4'],
            ['code' => '4102', 'name' => 'مردودات ومسموحات المبيعات', 'type' => 'revenue', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '4'], // طبيعة عكسية
            ['code' => '4103', 'name' => 'الخصم المسموح به للعملاء', 'type' => 'revenue', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '4'], // طبيعة عكسية

            // ==========================================
            // 5. تكلفة المبيعات (COGS) - طبيعتها: مدين (Debit)
            // ==========================================
            ['code' => '5', 'name' => 'تكلفة المبيعات', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => false, 'parent_code' => null],
            ['code' => '5101', 'name' => 'تكلفة البضاعة المباعة (COGS)', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '5'],
            ['code' => '5102', 'name' => 'حساب المشتريات (للجرد الدوري)', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '5'],
            ['code' => '5103', 'name' => 'مصروفات مقاولي الباطن', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '5'],
            ['code' => '5104', 'name' => 'أجور مباشرة (تصنيع)', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '5'],
            ['code' => '5105', 'name' => 'تكاليف صناعية غير مباشرة (Overhead)', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '5'],

            // ==========================================
            // 6. المصروفات التشغيلية (Expenses) - طبيعتها: مدين (Debit)
            // ==========================================
            ['code' => '6', 'name' => 'المصروفات التشغيلية', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => false, 'parent_code' => null],

            // 61. المصروفات العمومية والإدارية (رواتب وإيجارات)
            ['code' => '61', 'name' => 'المصروفات العمومية والإدارية', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => false, 'parent_code' => '6'],
            ['code' => '6101', 'name' => 'مصروف الرواتب والأجور الأساسية', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '61'],
            ['code' => '6102', 'name' => 'مصروف بدلات السكن', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '61'],
            ['code' => '6103', 'name' => 'مصروف بدل النقل', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '61'],
            ['code' => '6104', 'name' => 'مصروف التأمين الطبي', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '61'],
            ['code' => '6105', 'name' => 'مصروف العمل الإضافي', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '61'],
            ['code' => '6106', 'name' => 'مصروف مكافأة نهاية الخدمة', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '61'],
            ['code' => '6107', 'name' => 'مصروف التأمينات (حصة الشركة)', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '61'],
            ['code' => '6108', 'name' => 'مصروف الإيجارات والكهرباء', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '61'],
            ['code' => '6109', 'name' => 'العمولات والمصاريف البنكية', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '61'],

            // 62. الإهلاك
            ['code' => '62', 'name' => 'مصروفات الإهلاك', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => false, 'parent_code' => '6'],
            ['code' => '6201', 'name' => 'مصروف الإهلاك', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '62'],

            // 63. مصروفات الحركة والسيارات (Fleet)
            ['code' => '63', 'name' => 'مصروفات الحركة والسيارات', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => false, 'parent_code' => '6'],
            ['code' => '6301', 'name' => 'مصروف محروقات وزيوت', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '63'],
            ['code' => '6302', 'name' => 'مصروف صيانة وإصلاح سيارات', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '63'],
            ['code' => '6303', 'name' => 'مصروف تأمين سيارات', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '63'],

            // ==========================================
            // 7. إيرادات أخرى / غير تشغيلية - طبيعتها: دائن (Credit)
            // ==========================================
            ['code' => '7', 'name' => 'إيرادات وأرباح أخرى', 'type' => 'revenue', 'nature' => 'credit', 'is_transactional' => false, 'parent_code' => null],
            ['code' => '7101', 'name' => 'أرباح فروق العملة (محققة)', 'type' => 'revenue', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '7'],
            ['code' => '7102', 'name' => 'أرباح فروق العملة (غير محققة/إعادة تقييم)', 'type' => 'revenue', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '7'],
            ['code' => '7103', 'name' => 'الخصم المكتسب من الموردين', 'type' => 'revenue', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '7'],
            ['code' => '7104', 'name' => 'أرباح تسويات وجرد المخزون', 'type' => 'revenue', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '7'],
            ['code' => '7105', 'name' => 'إيرادات أخرى (جزاءات وخصومات موظفين)', 'type' => 'revenue', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '7'],
            ['code' => '7106', 'name' => 'أرباح استبعاد/بيع الأصول الثابتة', 'type' => 'revenue', 'nature' => 'credit', 'is_transactional' => true, 'parent_code' => '7'],

            // ==========================================
            // 8. مصروفات أخرى / غير تشغيلية - طبيعتها: مدين (Debit)
            // ==========================================
            ['code' => '8', 'name' => 'مصروفات وخسائر أخرى', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => false, 'parent_code' => null],
            ['code' => '8101', 'name' => 'خسائر فروق العملة (محققة)', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '8'],
            ['code' => '8102', 'name' => 'خسائر فروق العملة (غير محققة/إعادة تقييم)', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '8'],
            ['code' => '8103', 'name' => 'خسائر تسويات وجرد المخزون', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '8'],
            ['code' => '8104', 'name' => 'خسائر استبعاد الأصول الثابتة', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '8'],
            ['code' => '8105', 'name' => 'مخالفات مرورية (مصاريف غير قابلة للخصم)', 'type' => 'expense', 'nature' => 'debit', 'is_transactional' => true, 'parent_code' => '8'],
        ];

        $insertedAccounts = [];

        foreach ($accounts as $data) {
            $parentId = null;
            if ($data['parent_code']) {
                $parentId = $insertedAccounts[$data['parent_code']] ?? Account::where('code', $data['parent_code'])->value('id');
            }

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

            $insertedAccounts[$data['code']] = $account->id;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('تم تهيئة شجرة الحسابات (المعيار العالمي ERP) بنجاح.');
    }
}
