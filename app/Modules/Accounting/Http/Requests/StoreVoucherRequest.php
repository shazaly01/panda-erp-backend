<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Modules\Accounting\Enums\VoucherType;
use Illuminate\Validation\Validator;

class StoreVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        // الصلاحية نتحقق منها في الكنترولر عبر Policy، هنا نعيد true
        return true;
    }

    public function rules(): array
    {
        return [
            // --- بيانات رأس السند ---

            // الفرع (يجب أن يكون موجوداً في جدول مراكز التكلفة)
            'branch_id' => ['required', 'exists:cost_centers,id'],

            // النوع: صرف أو قبض
            'type' => ['required', new Enum(VoucherType::class)],

            // التاريخ
            'date' => ['required', 'date'],

            'payee_name' => ['required', 'string', 'max:255'],

            // الوصف العام
            'description' => ['nullable', 'string', 'max:500'],

            // --- وسيلة الدفع (المنطق المعقد) ---
            // يجب اختيار خزينة أو بنك، ولكن ليس كلاهما معاً (سنتحقق منها في withValidator)
            'box_id' => ['nullable', 'exists:boxes,id'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],

            // العملة والمبلغ
            'currency_id' => ['required', 'exists:currencies,id'],
            'exchange_rate' => ['required', 'numeric', 'min:0.0001'],
            'amount' => ['required', 'numeric', 'min:0.01'], // إجمالي السند

            // --- التفاصيل (السطور) ---
            'details' => ['required', 'array', 'min:1'], // يجب وجود سطر واحد على الأقل
            'details.*.account_id' => ['required', 'exists:accounts,id'], // الحساب المستفيد
            'details.*.cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'details.*.amount' => ['required', 'numeric', 'min:0.01'],
            'details.*.description' => ['nullable', 'string', 'max:255'],

            // 👇 الإضافات الجديدة هنا لربط الأطراف (الموظفين/الموردين/العملاء)
            'details.*.party_type' => ['nullable', 'string', 'max:255'],
            'details.*.party_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * تحقق إضافي مخصص (Advanced Validation Logic)
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // 1. التحقق من اختيار وسيلة دفع واحدة فقط (خزينة أو بنك)
            $box = $this->input('box_id');
            $bank = $this->input('bank_account_id');

            if (!$box && !$bank) {
                $validator->errors()->add('payment_method', 'يجب تحديد وسيلة الدفع (خزينة أو حساب بنكي).');
            }

            if ($box && $bank) {
                $validator->errors()->add('payment_method', 'لا يمكن اختيار خزينة وحساب بنكي في نفس الوقت.');
            }

            // 2. التحقق المحاسبي: هل مجموع التفاصيل يساوي الإجمالي؟
            // هذه أهم قاعدة لمنع الأخطاء المحاسبية
            $totalAmount = (float) $this->input('amount');
            $detailsSum = collect($this->input('details'))->sum('amount');

            // نستخدم هامش خطأ صغير جداً بسبب مشاكل الفواصل العشرية في الكمبيوتر (Float Precision)
            if (abs($totalAmount - $detailsSum) > 0.001) {
                $validator->errors()->add('amount', "مجموع مبالغ التفاصيل ({$detailsSum}) لا يساوي إجمالي السند ({$totalAmount}).");
            }
        });
    }

    public function attributes(): array
    {
        return [
            'branch_id' => 'الفرع',
            'box_id' => 'الخزينة',
            'bank_account_id' => 'الحساب البنكي',
            'payee_name' => 'اسم المستفيد / الدافع', // <--- إضافة هذا السطر لتظهر رسالة الخطأ بشكل أنيق
            'details' => 'تفاصيل السند',
            'details.*.account_id' => 'الحساب في السطر',

            // 👇 إضافة ترجمة أنيقة للحقول الجديدة
            'details.*.party_type' => 'نوع الطرف المستفيد',
            'details.*.party_id' => 'رقم الطرف المستفيد',
        ];
    }
}
