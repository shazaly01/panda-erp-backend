<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Enums;

enum CostCenterType: string
{
    case Department = 'department'; // قسم إداري (HR, IT, Sales)
    case Project = 'project';       // مشروع له بداية ونهاية
    case Vehicle = 'vehicle';       // سيارة/معدة (لتتبع مصاريفها)

    public function label(): string
    {
        return match($this) {
            self::Department => 'قسم',
            self::Project => 'مشروع',
            self::Vehicle => 'معدة/سيارة',
        };
    }
}
