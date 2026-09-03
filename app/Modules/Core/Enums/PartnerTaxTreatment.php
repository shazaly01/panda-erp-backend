<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

enum PartnerTaxTreatment: string
{
    case TAXABLE = 'taxable';
    case EXEMPT = 'exempt';
    case ZERO_RATED = 'zero_rated';
    case NON_TAXABLE = 'non_taxable';
}