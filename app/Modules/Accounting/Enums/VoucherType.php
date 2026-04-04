<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Enums;

enum VoucherType: string
{
    case Payment = 'payment'; // سند صرف
    case Receipt = 'receipt'; // سند قبض

    // مستقبلاً يمكن تفعيل هذه الأنواع دون مشاكل
    // case Transfer = 'transfer'; // تحويل نقدية
    // case Opening = 'opening';   // رصيد افتتاحي للخزينة

    public function label(): string
    {
        return match($this) {
            self::Payment => 'سند صرف',
            self::Receipt => 'سند قبض',
        };
    }
}
