<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Modules\Core\Enums\PartnerType;
use App\Modules\Core\Enums\PartnerStatus;
use App\Modules\Core\Enums\PartnerTaxTreatment;

class StorePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(PartnerType::class)],
            'name' => ['required', 'string', 'max:255'],
            'commercial_name' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'commercial_registry' => ['nullable', 'string', 'max:50'],
            'tax_treatment' => ['nullable', new Enum(PartnerTaxTreatment::class)],
            'is_customer' => ['boolean'],
            'is_supplier' => ['boolean'],
            'status' => ['sometimes', new Enum(PartnerStatus::class)],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'credit_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'receivable_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'payable_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'iban' => ['nullable', 'string', 'max:50'],
            'swift_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $isCustomer = $this->boolean('is_customer');
            $isSupplier = $this->boolean('is_supplier');

            if (! $isCustomer && ! $isSupplier) {
                $validator->errors()->add('roles', 'يجب تحديد دور واحد على الأقل للطرف (عميل أو مورد).');
            }
        });
    }
}