<?php

declare(strict_types=1);

namespace App\Modules\HR\Enums;

enum SalaryRuleType: string
{
    case Fixed = 'fixed';           // مبلغ ثابت (مثلاً: بدل انتقال 500)
    case Percentage = 'percentage'; // نسبة مئوية (مثلاً: 10% من الأساسي)
    case Formula = 'formula';       // معادلة رياضية معقدة
    case Input = 'input';           // قيمة متغيرة تأتي من خارج النظام (سلفة، غياب، جمعية)

    public function label(): string
    {
        return match($this) {
            self::Fixed => 'مبلغ ثابت',
            self::Percentage => 'نسبة مئوية',
            self::Formula => 'معادلة',
            self::Input => 'مدخل خارجي',
        };
    }
}
