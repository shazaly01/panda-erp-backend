<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Modules\Accounting\Models\FiscalYear;

class PeriodIsOpenRule implements ValidationRule
{
   /**
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // $value هو التاريخ المرسل في القيد (2025-01-01)

        if (!FiscalYear::checkDate((string)$value)) {
            $fail('التاريخ المدخل لا يقع ضمن أي سنة مالية مفتوحة.');
        }
    }
}
