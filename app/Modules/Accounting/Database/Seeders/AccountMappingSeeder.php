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
            // هذا هو السطر الذي سيخبر النظام: "أي خزينة جديدة، ضعها تحت الحساب 101"
            'default_box_parent'    => ['name' => 'نقدية بالصناديق (رئيسي)', 'code' => '1002001001'],

            // هذا هو السطر الذي سيخبر النظام: "أي بنك جديد، ضعه تحت الحساب 102"
            'default_bank_parent'   => ['name' => 'نقدية بالبنوك (رئيسي)', 'code' => '1002001002'],

            // ==========================================
            // 1. مفاتيح الموارد البشرية (HR & Payroll)
            // ==========================================
            'hr_salaries_payable'   => ['name' => 'رواتب مستحقة الدفع (الصافي)', 'code' => '2105'], // التزام
            'hr_basic_salary'       => ['name' => 'مصروف الرواتب الأساسية', 'code' => '4101'],      // مصروف
            'hr_housing_allowance'  => ['name' => 'مصروف بدل السكن', 'code' => '4102'],             // مصروف
            'hr_transport_allowance'=> ['name' => 'مصروف بدل النقل', 'code' => '4103'],             // مصروف
            'hr_gosi_deduction'     => ['name' => 'التأمينات الاجتماعية (ذمم)', 'code' => '2106'],  // التزام

            // ==========================================
            // 2. مفاتيح المبيعات (Sales)
            // ==========================================
            'sales_revenue'         => ['name' => 'إيرادات المبيعات', 'code' => '5101'],
            'sales_returns'         => ['name' => 'مردودات المبيعات', 'code' => '5102'],
            'accounts_receivable'   => ['name' => 'العملاء (ذمم مدينة)', 'code' => '1201'],
            'vat_output'            => ['name' => 'ضريبة مخرجات (على المبيعات)', 'code' => '2301'],

            // ==========================================
            // 3. مفاتيح المشتريات والمخازن (Purchases & Inventory)
            // ==========================================
            'accounts_payable'      => ['name' => 'الموردين (ذمم دائنة)', 'code' => '2101'], // <-- تمت إعادته
            'inventory_asset'       => ['name' => 'حساب المخزون (أصول)', 'code' => '1205'],
            'cost_of_goods'         => ['name' => 'تكلفة البضاعة المباعة', 'code' => '6101'],
            'vat_input'             => ['name' => 'ضريبة مدخلات (على المشتريات)', 'code' => '2302'],
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
            }
        }
    }
}
