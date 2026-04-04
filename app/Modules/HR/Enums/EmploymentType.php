<?php

declare(strict_types=1);

namespace App\Modules\HR\Enums;

enum EmploymentType: string
{
    case FullTime = 'full_time'; // دوام كامل
    case PartTime = 'part_time'; // دوام جزئي
    case Contract = 'contract';  // تعاقد (مشروع)
    case Intern = 'intern';      // متدرب

    public function label(): string
    {
        return match($this) {
            self::FullTime => 'دوام كامل',
            self::PartTime => 'دوام جزئي',
            self::Contract => 'تعاقد',
            self::Intern => 'تدريب',
        };
    }
}
