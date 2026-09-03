<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Enums;

enum PurchaseOrderStatus: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case PARTIALLY_RECEIVED = 'partially_received';
    case RECEIVED = 'received';
    case PARTIALLY_BILLED = 'partially_billed';
    case BILLED = 'billed';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة',
            self::CONFIRMED => 'مؤكد',
            self::PARTIALLY_RECEIVED => 'مستلم جزئياً',
            self::RECEIVED => 'مستلم بالكامل',
            self::PARTIALLY_BILLED => 'مفوتر جزئياً',
            self::BILLED => 'مفوتر بالكامل',
            self::CLOSED => 'مغلق',
            self::CANCELLED => 'ملغي',
        };
    }
}