<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Enums;

enum FiscalYearStatus: string
{
    case Open = 'open';     // مفتوحة: تقبل القيود
    case Closed = 'closed'; // مغلقة: لا تقبل أي قيد (تم ترحيل الأرباح والخسائر)
    case Locked = 'locked'; // مجمدة: مراجعة نهائية (تقبل قيود بصلاحيات خاصة فقط)

    public function label(): string
    {
        return match($this) {
            self::Open => 'مفتوحة',
            self::Closed => 'مغلقة',
            self::Locked => 'مجمدة مؤقتاً',
        };
    }
}
