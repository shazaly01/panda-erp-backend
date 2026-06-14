<?php

namespace App\Modules\HR\Http\Requests\Visitor;

use Illuminate\Foundation\Http\FormRequest;

class GateActionRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مخولاً لإجراء هذا الطلب.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق المعتمدة على المفاتيح التناوبية (ID أو Token).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required_without:qr_token', 'integer', 'exists:hr_visitors,id'],
            'qr_token' => ['required_without:id', 'string', 'exists:hr_visitors,qr_token'],
        ];
    }
}
