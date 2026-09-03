<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Enums;

enum BudgetControlMode: string
{
    case Advisory = 'advisory';
    case Warning = 'warning';
    case StrictStop = 'strict_stop';

    public function label(): string
    {
        return match ($this) {
            self::Advisory => 'استرشادي / تقارير فقط',
            self::Warning => 'تحذير عند التجاوز',
            self::StrictStop => 'منع صارم عند التجاوز',
        };
    }
}