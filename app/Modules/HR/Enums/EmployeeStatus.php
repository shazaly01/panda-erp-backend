<?php

declare(strict_types=1);

namespace App\Modules\HR\Enums;

enum EmployeeStatus: string
{
    case InService = 'in_service';
    case Leave = 'leave';
    case Dismissed = 'dismissed';
    case EndOfService = 'end_of_service';
    case TemporarilyDismissed = 'temporarily_dismissed';
    case Training = 'training';
    case TemporaryTransfer = 'temporary_transfer';
    case MonthlyTemporaryTransfer = 'monthly_temporary_transfer';

    public function label(): string
    {
        return match($this) {
            self::InService => 'في الخدمة',
            self::Leave => 'إجاره',
            self::Dismissed => 'مفصول',
            self::EndOfService => 'انتهاء خدمة',
            self::TemporarilyDismissed => 'مفصول مؤقتا',
            self::Training => 'تحت التدريب',
            self::TemporaryTransfer => 'تحويل لفتره موقته',
            self::MonthlyTemporaryTransfer => 'تحويل لفتره موقته شهري',
        };
    }
}
