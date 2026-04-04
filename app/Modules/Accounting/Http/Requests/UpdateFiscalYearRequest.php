<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Enums\FiscalYearStatus;

class UpdateFiscalYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('fiscal_year'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date', 'after:start_date'],
            'status' => ['sometimes', 'required', Rule::enum(FiscalYearStatus::class)],
        ];
    }
}
