<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;
use App\Modules\Accounting\Enums\BudgetControlMode;
use App\Modules\Accounting\Enums\BudgetPeriodType;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Enums\AccountType;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'period_type' => ['required', new Enum(BudgetPeriodType::class)],
            'control_mode' => ['required', new Enum(BudgetControlMode::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['nullable', 'required_without:lines.*.cost_center_id', 'integer', 'exists:accounts,id'],
            'lines.*.cost_center_id' => ['nullable', 'required_without:lines.*.account_id', 'integer', 'exists:cost_centers,id'],
            'lines.*.period_start' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:end_date'],
            'lines.*.period_end' => ['required', 'date', 'after_or_equal:lines.*.period_start', 'before_or_equal:end_date'],
            'lines.*.planned_amount' => ['required', 'numeric', 'min:0'],
            'lines.*.notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.*.account_id.required_without' => 'يجب تحديد الحساب المالي أو مركز التكلفة على الأقل لكل بند.',
            'lines.*.cost_center_id.required_without' => 'يجب تحديد مركز التكلفة أو الحساب المالي على الأقل لكل بند.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $lines = $this->input('lines', []);
                if (! is_array($lines) || empty($lines)) {
                    return;
                }

                $accountIds = array_filter(array_column($lines, 'account_id'));
                if (empty($accountIds)) {
                    return;
                }

                $accounts = Account::whereIn('id', $accountIds)->get()->keyBy('id');

                foreach ($lines as $index => $line) {
                    $accountId = $line['account_id'] ?? null;
                    if (! $accountId || ! isset($accounts[$accountId])) {
                        continue;
                    }

                    $account = $accounts[$accountId];

                    if (! $account->is_transactional) {
                        $validator->errors()->add(
                            "lines.{$index}.account_id",
                            'الحساب المالي المحدد يجب أن يكون حساباً فرعياً يقبل الحركات.'
                        );
                    }

                    if (! in_array($account->type, [AccountType::EXPENSE, AccountType::REVENUE], true)) {
                        $validator->errors()->add(
                            "lines.{$index}.account_id",
                            'الموازنة التقديرية تُقبل فقط لحسابات المصروفات أو الإيرادات.'
                        );
                    }
                }
            },
        ];
    }
}