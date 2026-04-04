<?php

namespace App\Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'number'        => $this->number, // الرقم المميز (RY-PAY-001)
            'date'          => $this->date->format('Y-m-d'),

            // النوع (صرف/قبض)
            'type'          => $this->type->value,
            'type_label'    => $this->type->label(),

            'payee_name'    => $this->payee_name,

            'description'   => $this->description,
            'amount'        => (float) $this->amount,
            'exchange_rate' => (float) $this->exchange_rate,

            // الحالة (مع اللون للعرض في الجدول)
            'status'        => $this->status->value,
            'status_label'  => $this->status->label(),
            'status_color'  => $this->status->color(), // (gray, green, red...)

            // الفرع (مركز التكلفة)
            'branch'        => $this->whenLoaded('branch', function () {
                return [
                    'id'   => $this->branch->id,
                    'name' => $this->branch->name,
                    'code' => $this->branch->code_prefix,
                ];
            }),

            // العملة
            'currency'      => new CurrencyResource($this->whenLoaded('currency')),

            // تحديد وسيلة الدفع للعرض
            'payment_method' => $this->getPaymentMethodData(),

            // التفاصيل (نستخدم الريسورس الصغير الذي أنشأناه قبل قليل)
            'details'       => VoucherDetailResource::collection($this->whenLoaded('details')),

            // بيانات التدقيق (من أنشأ ومن رحل)
            'audit' => [
                'created_at' => $this->created_at->toDateTimeString(),
                'posted_at'  => $this->posted_at?->toDateTimeString(),
                // يمكن إضافة created_by_user هنا إذا كانت العلاقة موجودة
            ],
        ];
    }

    /**
     * دالة مساعدة لتجهيز بيانات الدفع (خزينة أو بنك)
     */
   protected function getPaymentMethodData(): ?array
    {
        if ($this->box_id && $this->relationLoaded('box')) {
            return [
                'type' => 'box',
                'id'   => $this->box->id,
                'name' => $this->box->name,
            ];
        }

        // تم تصحيح اسم الحقل هنا إلى bank_account_id
        if ($this->bank_account_id && $this->relationLoaded('bankAccount')) {
            return [
                'type' => 'bank',
                'id'   => $this->bankAccount->id,
                'name' => $this->bankAccount->bank_name . ' - ' . $this->bankAccount->account_number,
            ];
        }

        return null;
    }
}
