<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Purchasing\Models\PurchaseBill
 */
class PurchaseBillResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bill_number' => $this->bill_number,
            'supplier_bill_number' => $this->supplier_bill_number,
            'supplier_id' => $this->supplier_id,
            'warehouse_id' => $this->warehouse_id,
            'purchase_order_id' => $this->purchase_order_id,
            'receipt_id' => $this->receipt_id,
            'currency_id' => $this->currency_id,
            'exchange_rate' => (float) $this->exchange_rate,
            'bill_date' => $this->bill_date?->toDateString() ?? (string) $this->bill_date,
            'due_date' => $this->due_date?->toDateString() ?? (string) $this->due_date,
            
            // حالة الفاتورة
            'status' => [
                'value' => $this->status->value ?? $this->status,
                'label' => method_exists($this->status, 'label') ? $this->status->label() : (string) $this->status,
            ],

            // القيم المالية المجمعة
            'subtotal' => (float) $this->subtotal,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'shipping_cost' => (float) $this->shipping_cost,
            'total_amount' => (float) $this->total_amount,
            'paid_amount' => (float) $this->paid_amount,
            'remaining_amount' => (float) $this->remaining_amount,
            'notes' => $this->notes,

            // بيانات الترحيل والمستخدمين
            'posted_at' => $this->posted_at?->toIso8601String(),
            'posted_by' => $this->posted_by,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // علاقة المورد مع تمرير حساب الذمم المالي لدعم سندات الصرف
            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'id' => $this->supplier->id,
                    'partner_code' => $this->supplier->partner_code,
                    'name' => $this->supplier->name,
                    'commercial_name' => $this->supplier->commercial_name,
                    'tax_number' => $this->supplier->tax_number,
                    'phone' => $this->supplier->phone,
                    'email' => $this->supplier->email,
                    'account_id' => $this->supplier->payable_account_id,
                    'payable_account_id' => $this->supplier->payable_account_id,
                    'account' => $this->supplier->relationLoaded('payableAccount') && $this->supplier->payableAccount ? [
                        'id' => $this->supplier->payableAccount->id,
                        'code' => $this->supplier->payableAccount->code,
                        'name' => $this->supplier->payableAccount->name,
                    ] : null,
                ];
            }),

            // علاقة المستودع
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id' => $this->warehouse->id,
                    'code' => $this->warehouse->code,
                    'name' => $this->warehouse->name,
                ];
            }),

            // علاقة العملة
            'currency' => $this->whenLoaded('currency', function () {
                return [
                    'id' => $this->currency->id,
                    'code' => $this->currency->code,
                    'name' => $this->currency->name,
                    'symbol' => $this->currency->symbol,
                ];
            }),

            // علاقة أمر الشراء وسند الاستلام إن وجدا
            'purchase_order' => $this->whenLoaded('purchaseOrder', function () {
                return [
                    'id' => $this->purchaseOrder->id,
                    'order_number' => $this->purchaseOrder->order_number,
                    'status' => $this->purchaseOrder->status?->value ?? $this->purchaseOrder->status,
                ];
            }),

            'receipt' => $this->whenLoaded('receipt', function () {
                return [
                    'id' => $this->receipt->id,
                    'receipt_number' => $this->receipt->receipt_number,
                    'status' => $this->receipt->status?->value ?? $this->receipt->status,
                ];
            }),

            // المستخدمون ذوو الصلة
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ];
            }),

            'poster' => $this->whenLoaded('poster', function () {
                return [
                    'id' => $this->poster->id,
                    'name' => $this->poster->name,
                ];
            }),

            // بنود الفاتورة المفصلة
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'purchase_bill_id' => $item->purchase_bill_id,
                        'purchase_order_item_id' => $item->purchase_order_item_id,
                        'receipt_item_id' => $item->receipt_item_id,
                        'product_id' => $item->product_id,
                        'product_unit_id' => $item->product_unit_id,
                        'account_id' => $item->account_id,
                        'quantity' => (float) $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'discount_percentage' => (float) $item->discount_percentage,
                        'discount_amount' => (float) $item->discount_amount,
                        'tax_rate' => (float) $item->tax_rate,
                        'tax_amount' => (float) $item->tax_amount,
                        'subtotal' => (float) $item->subtotal,
                        'total' => (float) $item->total,
                        'notes' => $item->notes,
                        'product' => $item->relationLoaded('product') && $item->product ? [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'code' => $item->product->code,
                            'barcode' => $item->product->barcode,
                        ] : null,
                        'product_unit' => $item->relationLoaded('productUnit') && $item->productUnit ? [
                            'id' => $item->productUnit->id,
                            'name' => $item->productUnit->name,
                            'conversion_factor' => (float) $item->productUnit->conversion_factor,
                        ] : null,
                        'account' => $item->relationLoaded('account') && $item->account ? [
                            'id' => $item->account->id,
                            'code' => $item->account->code,
                            'name' => $item->account->name,
                        ] : null,
                    ];
                });
            }),

            // سندات الصرف المرتبطة بالفاتورة
            'voucher_details' => $this->whenLoaded('voucherDetails', function () {
                return $this->voucherDetails->map(function ($detail) {
                    return [
                        'id' => $detail->id,
                        'voucher_id' => $detail->voucher_id,
                        'amount' => (float) $detail->amount,
                        'description' => $detail->description,
                        'voucher' => $detail->relationLoaded('voucher') && $detail->voucher ? [
                            'id' => $detail->voucher->id,
                            'voucher_number' => $detail->voucher->voucher_number,
                            'voucher_date' => $detail->voucher->voucher_date?->toDateString() ?? (string) $detail->voucher->voucher_date,
                            'payment_type' => $detail->voucher->payment_type,
                            'status' => $detail->voucher->status?->value ?? $detail->voucher->status,
                            'box' => $detail->voucher->relationLoaded('box') && $detail->voucher->box ? [
                                'id' => $detail->voucher->box->id,
                                'name' => $detail->voucher->box->name,
                            ] : null,
                            'bank_account' => $detail->voucher->relationLoaded('bankAccount') && $detail->voucher->bankAccount ? [
                                'id' => $detail->voucher->bankAccount->id,
                                'bank_name' => $detail->voucher->bankAccount->bank_name,
                                'account_number' => $detail->voucher->bankAccount->account_number,
                            ] : null,
                        ] : null,
                    ];
                });
            }),

            // القيود المحاسبية المتولدة
            'journal_entries' => $this->whenLoaded('journalEntries', function () {
                return $this->journalEntries->map(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'entry_number' => $entry->entry_number,
                        'entry_date' => $entry->entry_date?->toDateString() ?? (string) $entry->entry_date,
                        'status' => $entry->status?->value ?? $entry->status,
                        'details' => $entry->relationLoaded('details') ? $entry->details->map(function ($d) {
                            return [
                                'id' => $d->id,
                                'account_id' => $d->account_id,
                                'account_name' => $d->account?->name,
                                'account_code' => $d->account?->code,
                                'debit' => (float) $d->debit,
                                'credit' => (float) $d->credit,
                                'description' => $d->description,
                            ];
                        }) : [],
                    ];
                });
            }),

            // الحركات المخزنية المباشرة
            'stock_movements' => $this->whenLoaded('stockMovements', function () {
                return $this->stockMovements->map(function ($m) {
                    return [
                        'id' => $m->id,
                        'product_id' => $m->product_id,
                        'warehouse_id' => $m->warehouse_id,
                        'movement_type' => $m->movement_type,
                        'quantity' => (float) $m->quantity,
                        'unit_cost' => (float) $m->unit_cost,
                        'created_at' => $m->created_at?->toIso8601String(),
                    ];
                });
            }),
        ];
    }
}