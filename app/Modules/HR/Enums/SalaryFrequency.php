<?php

declare(strict_types=1);

namespace App\Modules\HR\Enums;

enum SalaryFrequency: string
{
    case Monthly = 'monthly';
    case BiWeekly = 'bi_weekly';
    case Weekly = 'weekly';
    case Daily = 'daily';

    // دالة إضافية مساعدة (اختيارية) لتسهيل عرضها في الواجهة الأمامية
    public function label(): string
    {
        return match($this) {
            self::Monthly => 'شهري',
            self::BiWeekly => 'نصف شهري (كل أسبوعين)',
            self::Weekly => 'أسبوعي',
            self::Daily => 'يومي',
        };
    }
}
