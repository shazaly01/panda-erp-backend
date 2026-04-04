<?php

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('currency'));
    }

    public function rules(): array
    {
        return [
            // نستثني العملة الحالية من فحص التكرار
            'code'          => ['required', 'string', 'size:3', 'uppercase', 'unique:currencies,code,' . $this->route('currency')->id],
            'name'          => ['required', 'string', 'max:50'],
            'symbol'        => ['required', 'string', 'max:10'],
            'exchange_rate' => ['required', 'numeric', 'min:0'],
            'is_base'       => ['boolean'],
            'is_active'     => ['boolean'],
        ];
    }
}
