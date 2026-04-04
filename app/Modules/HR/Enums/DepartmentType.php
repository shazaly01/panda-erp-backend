<?php

declare(strict_types=1);

namespace App\Modules\HR\Enums;

enum DepartmentType: string
{
    case Administration = 'administration'; // إدارة عليا
    case Department = 'department';         // قسم
    case Section = 'section';               // شعبة / وحدة
    case Branch = 'branch';                 // فرع

    public function label(): string
    {
        return match($this) {
            self::Administration => 'إدارة',
            self::Department => 'قسم',
            self::Section => 'شعبة/وحدة',
            self::Branch => 'فرع',
        };
    }
}
