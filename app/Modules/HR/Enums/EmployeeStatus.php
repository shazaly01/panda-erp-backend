<?php

declare(strict_types=1);

namespace App\Modules\HR\Enums;

enum EmployeeStatus: string
{
    case Active = 'active';           // على رأس العمل
    case OnLeave = 'on_leave';        // في إجازة
    case Resigned = 'resigned';       // مستقيل
    case Terminated = 'terminated';   // منهى خدماته
    case Probation = 'probation';     // فترة تجربة

    public function label(): string
    {
        return match($this) {
            self::Active => 'نشط',
            self::OnLeave => 'في إجازة',
            self::Resigned => 'مستقيل',
            self::Terminated => 'منهى خدماته',
            self::Probation => 'فترة تجربة',
        };
    }
}
