<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Enums;

enum BillStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case PAID = 'paid';
    case PARTIALLY_PAID = 'partially_paid';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة',
            self::POSTED => 'مرحلة',
            self::PAID => 'مدفوعة بالكامل',
            self::PARTIALLY_PAID => 'مدفوعة جزئياً',
            self::CANCELLED => 'ملغية',
        };
    }
}