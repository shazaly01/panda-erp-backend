<?php

declare(strict_types=1);

namespace App\Modules\HR\Enums;

enum PayrollRunType: string
{
    case Regular = 'regular';
    case OvertimeOnly = 'overtime_only';

    // دالة مساعدة لواجهة المستخدم
    public function label(): string
    {
        return match($this) {
            self::Regular => 'مسير اعتيادي (شامل)',
            self::OvertimeOnly => 'مسير إضافي فقط (Overtime)',
        };
    }
}
