<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Rules\PeriodIsOpenRule;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', JournalEntry::class);
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date', new PeriodIsOpenRule()],
            'description' => ['nullable', 'string', 'max:255'],
            'currency_id' => ['nullable', 'integer'], // يمكن إضافة exists:currencies,id

            // التحقق من التفاصيل (يجب أن يكون هناك طرفين على الأقل)
            'details' => ['required', 'array', 'min:2'],

            'details.*.account_id' => ['required', 'exists:accounts,id'],
            'details.*.cost_center_id' => ['nullable', 'exists:cost_centers,id'],

            // التحقق من أن القيم رقمية ولا تقل عن صفر
            'details.*.debit' => ['required', 'numeric', 'min:0'],
            'details.*.credit' => ['required', 'numeric', 'min:0'],

            // التحقق من الأطراف (Sub-Ledgers) - اختياري
            'details.*.party_type' => ['nullable', 'string'],
            'details.*.party_id' => ['nullable', 'integer'],
            'details.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * التحقق المنطقي المتقدم (توازن القيد)
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $details = $this->input('details', []);

            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($details as $index => $row) {
                // نجمع القيم (مع تحويلها لـ float لضمان الأمان)
                $debit = (float) ($row['debit'] ?? 0);
                $credit = (float) ($row['credit'] ?? 0);

                // قاعدة: لا يجوز أن يكون السطر مديناً ودائناً في نفس الوقت (إلا في حالات نادرة جداً، سنمنعها الآن للتبسيط)
                if ($debit > 0 && $credit > 0) {
                    $validator->errors()->add("details.$index", 'لا يمكن أن يكون السطر مديناً ودائناً في نفس الوقت.');
                }

                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            // السماح بفرق بسيط جداً (Floating Point Precision)
            if (abs($totalDebit - $totalCredit) > 0.0001) {
                $validator->errors()->add('balance', "القيد غير متزن. إجمالي المدين ($totalDebit) لا يساوي إجمالي الدائن ($totalCredit).");
            }
        });
    }
}
