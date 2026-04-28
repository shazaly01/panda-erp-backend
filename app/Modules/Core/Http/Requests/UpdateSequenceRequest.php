<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSequenceRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحاً له بإجراء هذا الطلب.
     * (التحقق من الصلاحيات يتم عبر الـ Controller باستخدام SequencePolicy)
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق التي تطبق على الطلب.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'format' => [
                'required',
                'string',
                'max:150',
                // هذه القاعدة تضمن وجود العداد الإلزامي (مثل {0000}) لكي لا يتعطل SequenceService
                'regex:/\{0+\}/',
                // 🌟 قاعدة إضافية للحماية: منع استخدام أقواس معقوفة لمتغيرات غير مدعومة في نظامنا
                // المسموح فقط: {Y}, {y}, {m}, {YM}, {PREFIX}, {0...0}
                'regex:/^[^\{]*(\{(Y|y|m|YM|PREFIX|0+)\}[^\{]*)+$/'
            ],
            'reset_frequency' => [
                'required',
                'string',
                'in:yearly,monthly,never',
            ],
            // السماح بتعديل الرقم القادم يدوياً (مثلاً للبدء من رقم 1000)
            'next_value' => [
                'sometimes',
                'integer',
                'min:1',
            ],
        ];
    }

    /**
     * رسائل الخطأ المخصصة للواجهة.
     */
    public function messages(): array
    {
        return [
            'format.required' => 'حقل صيغة الترقيم مطلوب.',
            'format.regex'    => 'صيغة الترقيم غير صحيحة. يجب أن تحتوي على عداد أصفار (مثل: {0000}) ويمكن استخدام المتغيرات المدعومة فقط: {Y}, {y}, {m}, {YM}, {PREFIX}.',
            'reset_frequency.required' => 'حقل وتيرة التصفير مطلوب.',
            'reset_frequency.in'       => 'وتيرة التصفير يجب أن تكون إحدى القيم التالية: yearly (سنوي), monthly (شهري), never (مطلقاً).',
            'next_value.integer'       => 'الرقم القادم يجب أن يكون رقماً صحيحاً.',
            'next_value.min'           => 'الرقم القادم يجب أن يكون 1 على الأقل.',
        ];
    }
}
