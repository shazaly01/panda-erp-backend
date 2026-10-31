<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum StockSerialStatus: string
{
    case IN_STOCK = 'in_stock';
    case IN_SERVICE = 'in_service';
    case EMPTY = 'empty';
    case WITH_VENDOR = 'with_vendor';
    case IN_MAINTENANCE = 'in_maintenance';
    case REPLACED = 'replaced';
    case SCRAPPED = 'scrapped';

    /**
     * التسمية العربية الصريحة للحالة
     */
    public function label(): string
    {
        return match ($this) {
            self::IN_STOCK => 'ممتلئة بالمستودع',
            self::IN_SERVICE => 'قيد التشغيل بالمحطة',
            self::EMPTY => 'فارغة بالمستودع',
            self::WITH_VENDOR => 'لدى المورد للتعبئة',
            self::IN_MAINTENANCE => 'تحت الصيانة والفحص',
            self::REPLACED => 'مستبدلة لدى المورد',
            self::SCRAPPED => 'تالفة / خارج الخدمة',
        };
    }

    /**
     * التحقق مما إذا كانت الأسطوانة جاهزة للصرف
     */
    public function isAvailable(): bool
    {
        return $this === self::IN_STOCK;
    }

    /**
     * التحقق مما إذا كانت الأسطوانة خارج عهدة المستودع المباشرة
     */
    public function isDispatched(): bool
    {
        return in_array($this, [self::IN_SERVICE, self::WITH_VENDOR], true);
    }
}