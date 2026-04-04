<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('company'));
    }

    public function rules(): array
    {
        $companyId = $this->route('company')->id;
        return [
            'name' => 'sometimes|required|string|max:255',
            'commercial_record' => 'nullable|string|max:255|unique:companies,commercial_record,' . $this->company->id,
            'tax_number' => 'sometimes|nullable|string|max:255|unique:companies,tax_number,' . $companyId,
            'license_number' => 'sometimes|nullable|string|max:255', // <-- [تمت الإضافة هنا]
            'address' => 'sometimes|nullable|string',
            'owner_name' => 'sometimes|nullable|string|max:255',
        ];
    }
}
