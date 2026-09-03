<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Enums;

enum BudgetPeriodType: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Annually = 'annually';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'شهري',
            self::Quarterly => 'ربع سنوي',
            self::Annually => 'سنوي',
            self::Custom => 'مخصص / مشاريع',
        };
    }
}