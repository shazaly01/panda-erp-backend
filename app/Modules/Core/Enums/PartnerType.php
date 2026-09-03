<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

enum PartnerType: string
{
    case PERSON = 'person';
    case COMPANY = 'company';
}