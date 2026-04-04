<?php

namespace App\Modules\Accounting\Enums;

enum AccountType: string
{
    case ASSET = 'asset';
    case LIABILITY = 'liability';
    case EQUITY = 'equity';
    case REVENUE = 'revenue';
    case EXPENSE = 'expense';

    public function label(): string
    {
        return match($this) {
            self::ASSET => 'أصول (Assets)',
            self::LIABILITY => 'خصوم (Liabilities)',
            self::EQUITY => 'حقوق ملكية (Equity)',
            self::REVENUE => 'إيرادات (Revenues)',
            self::EXPENSE => 'مصروفات (Expenses)',
        };
    }
}
