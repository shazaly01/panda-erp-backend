<?php

namespace App\Http\Requests\Company;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Company::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'commercial_record' => 'nullable|string|max:255|unique:companies,commercial_record',
            'tax_number' => 'nullable|string|max:255|unique:companies,tax_number',
            'license_number' => 'nullable|string|max:255', // <-- [تمت الإضافة هنا]
            'address' => 'nullable|string',
            'owner_name' => 'nullable|string|max:255',
        ];
    }
}
