<?php

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Modules\Accounting\Models\Currency;

class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Currency::class);
    }

    public function rules(): array
    {
        return [
            'code'          => ['required', 'string', 'size:3', 'unique:currencies,code', 'uppercase'], // USD
            'name'          => ['required', 'string', 'max:50'],
            'symbol'        => ['required', 'string', 'max:10'],
            'exchange_rate' => ['required', 'numeric', 'min:0'],
            'is_base'       => ['boolean'],
            'is_active'     => ['boolean'],
        ];
    }
}
