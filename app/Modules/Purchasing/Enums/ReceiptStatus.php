<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Enums;

enum ReceiptStatus: string
{
    case DRAFT = 'draft';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة',
            self::RECEIVED => 'تم الاستلام',
            self::CANCELLED => 'ملغي',
        };
    }
}