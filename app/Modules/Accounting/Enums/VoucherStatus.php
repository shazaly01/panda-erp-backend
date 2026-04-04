<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Enums;

enum VoucherStatus: string
{
    case Draft = 'draft';             // مسودة (تحت الإعداد)
    case PendingApproval = 'pending'; // بانتظار الاعتماد (أرسلها المحاسب للمدير)
    case Approved = 'approved';       // معتمد (وافق المدير لكن لم يرحل للقيد بعد)
    case Rejected = 'rejected';       // مرفوض (عاد للمحاسب للتصحيح)
    case Posted = 'posted';           // مرحل (تم إنشاء القيد المالي - نهائي)
    case Void = 'void';               // ملغي (بعد الترحيل تم إلغاؤه بقيد عكسي)

    public function label(): string
    {
        return match($this) {
            self::Draft => 'مسودة',
            self::PendingApproval => 'بانتظار الاعتماد',
            self::Approved => 'معتمد',
            self::Rejected => 'مرفوض',
            self::Posted => 'مرحل',
            self::Void => 'ملغي',
        };
    }

    // دالة مساعدة للألوان في الواجهة الأمامية (Front-end)
    public function color(): string
    {
        return match($this) {
            self::Draft => 'gray',
            self::PendingApproval => 'orange',
            self::Approved => 'blue',
            self::Rejected => 'red',
            self::Posted => 'green',
            self::Void => 'black',
        };
    }
}
