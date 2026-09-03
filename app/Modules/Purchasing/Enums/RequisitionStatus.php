<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Enums;

enum RequisitionStatus: string
{
    case DRAFT = 'draft';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case ORDERED = 'ordered';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة',
            self::PENDING_APPROVAL => 'بانتظار الاعتماد',
            self::APPROVED => 'معتمد',
            self::REJECTED => 'مرفوض',
            self::ORDERED => 'تم إصدار أمر شراء',
            self::CANCELLED => 'ملغي',
        };
    }
}