<?php

namespace App\Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Accounting\Models\AccountMapping;
use App\Modules\Accounting\Models\Account;

class AccountMappingSeeder extends Seeder
{
    public function run(): void
    {
        $mappings = [
            // ==========================================
            // الأساسيات (الصناديق والبنوك)
            // ==========================================
            'default_box_parent'       => ['name' => 'النقدية بالصناديق (رئيسي)', 'code' => '1101'],
            'default_bank_parent'      => ['name' => 'النقدية بالبنوك (رئيسي)', 'code' => '1102'],

            // ==========================================
            // 1. مفاتيح الموارد البشرية (HR & Payroll)
            // ==========================================

            // --- أ. المصروفات (Expenses) ---
            'hr_basic_salary'          => ['name' => 'مصروف الرواتب الأساسية', 'code' => '6101'],
            'hr_housing_allowance'     => ['name' => 'مصروف بدل السكن', 'code' => '6102'],
            'hr_transport_allowance'   => ['name' => 'مصروف بدل النقل', 'code' => '6103'],
            'hr_company_contribution'  => ['name' => 'مصروف تأمينات اجتماعية (حصة الشركة)', 'code' => '6104'],
            'hr_overtime_allowance'    => ['name' => 'مصروف العمل الإضافي', 'code' => '6108'],

            // --- ب. الالتزامات (Liabilities) ---
            'hr_salaries_payable'      => ['name' => 'رواتب مستحقة الدفع (الصافي)', 'code' => '2102'],
            'hr_gosi_deduction'        => ['name' => 'التأمينات الاجتماعية - ذمم (حصة الموظف)', 'code' => '2103'],
            'hr_contributions_payable' => ['name' => 'التزامات التأمينات (حصة الشركة)', 'code' => '2104'],

            // --- ج. الأصول والإيرادات (العهد والسلف والجزاءات) ---
            'hr_employee_loans'        => ['name' => 'سلف وعهد الموظفين (أصول מתداولة)', 'code' => '1105'],
            'hr_penalties_income'      => ['name' => 'إيرادات أخرى (خصومات وجزاءات)', 'code' => '4103'],

            // ==========================================
            // 2. مفاتيح المبيعات (Sales)
            // ==========================================
            'sales_revenue'            => ['name' => 'إيرادات المبيعات', 'code' => '4101'],
            'sales_returns'            => ['name' => 'مردودات المبيعات', 'code' => '4102'],
            'accounts_receivable'      => ['name' => 'العملاء (ذمم مدينة)', 'code' => '1103'],
            'vat_output'               => ['name' => 'ضريبة مخرجات (على المبيعات)', 'code' => '2105'],

            // ==========================================
            // 3. مفاتيح المشتريات والمخازن (Purchases & Inventory)
            // ==========================================
            'accounts_payable'         => ['name' => 'الموردين (ذمم دائنة)', 'code' => '2101'],
            'inventory_asset'          => ['name' => 'حساب المخزون (أصول)', 'code' => '1104'],
            'cost_of_goods'            => ['name' => 'تكلفة البضاعة المباعة', 'code' => '5101'],
            'vat_input'                => ['name' => 'ضريبة مدخلات (على المشتريات)', 'code' => '1106'],
        ];

        foreach ($mappings as $key => $data) {
            $account = Account::where('code', $data['code'])->first();

            if ($account) {
                AccountMapping::updateOrCreate(
                    ['key' => $key, 'branch_id' => null],
                    [
                        'account_id' => $account->id,
                        'name' => $data['name']
                    ]
                );
            } else {
                // رسالة تحذير في سطر الأوامر لتنبيه المطور إذا كان الحساب مفقوداً
                $this->command->warn("تحذير: الحساب بكود {$data['code']} غير موجود في شجرة الحسابات. تم تخطي مفتاح الربط '{$key}'.");
            }
        }

        $this->command->info('تم ربط جميع الحسابات بنجاح!');
    }
}
