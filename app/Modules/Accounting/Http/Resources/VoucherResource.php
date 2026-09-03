<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'number'        => $this->number,
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
            'status_color'  => $this->status->color(),

            // الفرع
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

            // تفاصيل السند
            'details'       => VoucherDetailResource::collection($this->whenLoaded('details')),

            // بيانات التدقيق
            'audit' => [
                'created_at' => $this->created_at->toDateTimeString(),
                'posted_at'  => $this->posted_at?->toDateTimeString(),
            ],
        ];
    }

    /**
     * دالة مساعدة لتجهيز بيانات الدفع (خزينة أو بنك) بأمان مع التحقق من وجود الكائن
     */
    protected function getPaymentMethodData(): ?array
    {
        if ($this->relationLoaded('box') && $this->box !== null) {
            return [
                'type' => 'box',
                'id'   => $this->box->id,
                'name' => $this->box->name,
            ];
        }

        if ($this->relationLoaded('bankAccount') && $this->bankAccount !== null) {
            return [
                'type' => 'bank',
                'id'   => $this->bankAccount->id,
                'name' => $this->bankAccount->bank_name . ' - ' . $this->bankAccount->account_number,
            ];
        }

        return null;
    }
}