<?php

declare(strict_types=1);

namespace App\Modules\HR\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match($this) {
            self::Male => 'ذكر',
            self::Female => 'أنثى',
        };
    }
}
