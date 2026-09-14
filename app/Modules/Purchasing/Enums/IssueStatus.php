<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Enums;

enum IssueStatus: string
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة',
            self::ISSUED => 'تم الصرف',
            self::CANCELLED => 'ملغي',
        };
    }
}