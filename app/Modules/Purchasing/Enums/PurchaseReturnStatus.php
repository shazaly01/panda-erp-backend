<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Enums;

enum PurchaseReturnStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة',
            self::POSTED => 'مرحل',
            self::CANCELLED => 'ملغي',
        };
    }
}