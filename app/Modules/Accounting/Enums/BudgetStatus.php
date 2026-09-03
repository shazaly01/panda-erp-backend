<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Enums;

enum BudgetStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Approved => 'معتمدة',
            self::Active => 'نشطة',
            self::Closed => 'مغلقة',
        };
    }
}