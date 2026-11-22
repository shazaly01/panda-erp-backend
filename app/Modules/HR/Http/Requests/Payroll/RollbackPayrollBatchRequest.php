<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Payroll;

use App\Modules\HR\Policies\PayrollPolicy;
use Illuminate\Foundation\Http\FormRequest;

class RollbackPayrollBatchRequest extends FormRequest
{
    /**
     * التحقق من صلاحية المستخدم للتراجع عن مسير رواتب معتمد
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('rollback', PayrollPolicy::class);
    }

    /**
     * قواعد التحقق الخاصة بالتراجع وعكس القيد المحاسبي
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * رسائل الخطأ باللغة العربية
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'يجب إدخال سبب واضح للتراجع عن مسير الرواتب المعتمد.',
            'reason.string'   => 'سبب التراجع يجب أن يكون نصاً صالحاً.',
            'reason.max'      => 'سبب التراجع يجب ألا يتجاوز 255 حرفاً.',
        ];
    }
}