<?php

declare(strict_types=1);

namespace App\Modules\HR\Enums;

enum SalaryRuleCategory: string
{
    case Allowance = 'allowance';   // استحقاق (يضاف للراتب)
    case Deduction = 'deduction';   // استقطاع (يخصم من الراتب)
    case CompanyContribution = 'company_contribution'; // مساهمة شركة (لا تؤثر على الصافي، تكلفة على الشركة)

    public function label(): string
    {
        return match($this) {
            self::Allowance => 'استحقاق',
            self::Deduction => 'استقطاع',
            self::CompanyContribution => 'مساهمة الشركة',
        };
    }
}
