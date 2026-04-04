<?php

declare(strict_types=1);

namespace App\Modules\HR\Enums;

enum MaritalStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case Divorced = 'divorced';
    case Widowed = 'widowed';

    public function label(): string
    {
        return match($this) {
            self::Single => 'أعزب/عزباء',
            self::Married => 'متزوج/ة',
            self::Divorced => 'مطلق/ة',
            self::Widowed => 'أرمل/ة',
        };
    }
}
