<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Modules\Core\Enums\PartnerType;
use App\Modules\Core\Enums\PartnerStatus;
use App\Modules\Core\Enums\PartnerTaxTreatment;

class UpdatePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', new Enum(PartnerType::class)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'commercial_name' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'commercial_registry' => ['nullable', 'string', 'max:50'],
            'tax_treatment' => ['nullable', new Enum(PartnerTaxTreatment::class)],
            'is_customer' => ['boolean'],
            'is_supplier' => ['boolean'],
            'status' => ['sometimes', 'required', new Enum(PartnerStatus::class)],
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
            if ($this->has('is_customer') || $this->has('is_supplier')) {
                $isCustomer = $this->has('is_customer') ? $this->boolean('is_customer') : (bool) $this->route('partner')?->is_customer;
                $isSupplier = $this->has('is_supplier') ? $this->boolean('is_supplier') : (bool) $this->route('partner')?->is_supplier;

                if (! $isCustomer && ! $isSupplier) {
                    $validator->errors()->add('roles', 'يجب أن يحتفظ الطرف بدور واحد على الأقل (عميل أو مورد).');
                }
            }
        });
    }
}