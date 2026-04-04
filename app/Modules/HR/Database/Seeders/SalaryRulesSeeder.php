<?php

namespace App\Modules\HR\Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\HR\Models\SalaryRule;
use App\Modules\HR\Enums\SalaryRuleCategory;
use App\Modules\HR\Enums\SalaryRuleType;

class SalaryRulesSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            // 1. الراتب الأساسي (حجر الزاوية)
            [
                'name' => 'الراتب الأساسي',
                'code' => 'BASIC',
                'category' => SalaryRuleCategory::Allowance,
                'type' => SalaryRuleType::Fixed, // قيمته تأتي من العقد، لكنه يعتبر مبلغاً ثابتاً
                'description' => 'الراتب الأساسي المتفق عليه في العقد',
                'sequence' => 1,
            ],
            // 2. بدل السكن (معادلة: 25% من الأساسي)
            [
                'name' => 'بدل السكن',
                'code' => 'HOUSING',
                'category' => SalaryRuleCategory::Allowance,
                'type' => SalaryRuleType::Formula,
                'formula_expression' => 'BASIC * 0.25', // معادلة
                'description' => '25% من الراتب الأساسي',
                'sequence' => 2,
            ],
            // 3. بدل النقل (مبلغ ثابت افتراضي)
            [
                'name' => 'بدل النقل',
                'code' => 'TRANS',
                'category' => SalaryRuleCategory::Allowance,
                'type' => SalaryRuleType::Fixed,
                'value' => 500.00, // قيمة افتراضية يمكن تعديلها في العقد
                'description' => 'بدل مواصلات ثابت',
                'sequence' => 3,
            ],
            // 4. التأمينات الاجتماعية (خصم 10% من الأساسي + السكن)
            [
                'name' => 'التأمينات الاجتماعية',
                'code' => 'GOSI',
                'category' => SalaryRuleCategory::Deduction,
                'type' => SalaryRuleType::Formula,
                'formula_expression' => '(BASIC + HOUSING) * 0.10',
                'description' => 'حصة الموظف في التأمينات',
                'sequence' => 10, // ترتيب متأخر ليحسب بعد البدلات
            ],
            // 5. خصم الغياب (Input: يأتي من الحضور والانصراف)
            [
                'name' => 'خصم الغياب',
                'code' => 'ABSENCE',
                'category' => SalaryRuleCategory::Deduction,
                'type' => SalaryRuleType::Input, // قيمة متغيرة
                'description' => 'يتم حسابه بناءً على أيام الغياب',
                'sequence' => 11,
            ],
            // 6. السلف (Input: يأتي من موديول السلف)
            [
                'name' => 'السلفيات',
                'code' => 'LOAN',
                'category' => SalaryRuleCategory::Deduction,
                'type' => SalaryRuleType::Input,
                'description' => 'أقساط السلف الشهرية',
                'sequence' => 12,
            ],
             // 7. الجمعية (Input: كما طلبت)
             [
                'name' => 'الجمعية',
                'code' => 'SOCIETY',
                'category' => SalaryRuleCategory::Deduction,
                'type' => SalaryRuleType::Input,
                'description' => 'مساهمة الموظف في الجمعية',
                'sequence' => 13,
            ],
        ];

        foreach ($rules as $rule) {
            // نستخدم updateOrCreate لتجنب التكرار عند تشغيل الـ Seeder أكثر من مرة
            SalaryRule::updateOrCreate(
                ['code' => $rule['code']], // البحث بالكود
                [
                    'name' => $rule['name'],
                    'category' => $rule['category'],
                    'type' => $rule['type'],
                    'value' => $rule['value'] ?? 0,
                    'formula_expression' => $rule['formula_expression'] ?? null,
                    'description' => $rule['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
