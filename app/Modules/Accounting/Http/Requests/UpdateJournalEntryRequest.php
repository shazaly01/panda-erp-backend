<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // نمرر القيد الحالي للسياسة (للتأكد أنه Draft)
        return $this->user()->can('update', $this->route('journal_entry'));
    }

    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'currency_id' => ['nullable', 'integer'],

            'details' => ['sometimes', 'required', 'array', 'min:2'],
            'details.*.id' => ['nullable', 'integer'], // ID السطر في حال التعديل
            'details.*.account_id' => ['required_with:details', 'exists:accounts,id'],
            'details.*.cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'details.*.debit' => ['required_with:details', 'numeric', 'min:0'],
            'details.*.credit' => ['required_with:details', 'numeric', 'min:0'],
            'details.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // نطبق نفس منطق التوازن هنا أيضاً
        $validator->after(function ($validator) {
            if (!$this->has('details')) {
                return;
            }

            $details = $this->input('details', []);
            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($details as $row) {
                $totalDebit += (float) ($row['debit'] ?? 0);
                $totalCredit += (float) ($row['credit'] ?? 0);
            }

            if (abs($totalDebit - $totalCredit) > 0.0001) {
                $validator->errors()->add('balance', "القيد غير متزن. المدين ($totalDebit) ≠ الدائن ($totalCredit).");
            }
        });
    }
}
