<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Enums\FiscalYearStatus;

class StoreFiscalYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FiscalYear::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            // تاريخ النهاية يجب أن يكون بعد تاريخ البداية
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['required', Rule::enum(FiscalYearStatus::class)],
        ];
    }
}
