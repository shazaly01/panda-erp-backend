<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Enums;

enum EntryStatus: string
{
    case Draft = 'draft';   // مسودة: قابل للتعديل والحذف، لا يظهر في التقارير النهائية
    case Posted = 'posted'; // مرحل: نهائي، ممنوع التعديل، يؤثر في القوائم المالية
    case Void = 'void';     // ملغي: يبقى كأثر في النظام ولكن رصيده صفر

    public function label(): string
    {
        return match($this) {
            self::Draft => 'مسودة',
            self::Posted => 'مرحل',
            self::Void => 'ملغي',
        };
    }
}
