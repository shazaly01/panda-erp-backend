<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

enum PartnerStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case BLOCKED = 'blocked';
}