<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Enums;

enum EntrySource: string
{
    case Manual = 'manual';         // قيد يدوي من المحاسب
    case Sales = 'sales';           // قيد آلي من فاتورة مبيعات
    case Purchases = 'purchases';   // قيد آلي من فاتورة مشتريات
    case Inventory = 'inventory';   // قيد آلي من حركة مخزنية (تسوية/تالف)
    case Payroll = 'payroll';       // قيد آلي من مسير الرواتب
    case OpeningBalance = 'opening'; // قيد افتتاحي (رصيد بداية المدة)

    public function label(): string
    {
        return match($this) {
            self::Manual => 'قيد يدوي',
            self::Sales => 'مبيعات',
            self::Purchases => 'مشتريات',
            self::Inventory => 'مخزون',
            self::Payroll => 'رواتب',
            self::OpeningBalance => 'رصيد افتتاحي',
        };
    }
}
