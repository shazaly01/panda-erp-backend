<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests\SystemSetting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'base_currency_id' => [
                'nullable',
                'integer',
                Rule::exists('currencies', 'id')->whereNull('deleted_at'),
            ],
            'active_modules' => [
                'required',
                'array',
                'min:1',
            ],
            'active_modules.*' => [
                'required',
                'string',
                Rule::in([
                    'core',
                    'accounting',
                    'inventory',
                    'purchasing',
                    'hr',
                ]),
            ],
            'company_name' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'base_currency_id.exists' => 'العملة الأساسية المختارة غير موجودة في النظام.',
            'active_modules.required' => 'يجب تحديد مصفوفة الموديولات المفعّلة.',
            'active_modules.array'    => 'صيغة الموديولات المفعّلة يجب أن تكون مصفوفة.',
            'active_modules.min'      => 'يجب تفعيل موديول واحد على الأقل.',
            'active_modules.*.in'     => 'أحد الموديولات المختارة غير صالح أو غير معرّف بالنظام.',
            'company_name.max'        => 'اسم المؤسسة لا يمكن أن يتجاوز 255 حرفاً.',
        ];
    }
}