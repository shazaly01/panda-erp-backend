<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests\Invoice;

use App\Modules\Inventory\Models\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'partner_id' => ['sometimes', 'required', 'integer', 'exists:partners,id'],
            'customer_name_text' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['nullable', 'integer'],
            'warehouse_id' => ['sometimes', 'required', 'integer', 'exists:inventory_warehouses,id'],
            'cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'channel' => ['nullable', 'string', 'in:standard,pos'],
            'order_type' => ['nullable', 'string', 'in:standard,dine_in,takeaway,delivery'],
            'table_label' => ['nullable', 'string', 'max:50'],
            'discount_type' => ['nullable', 'string', 'in:fixed,percentage'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'service_charge' => ['nullable', 'numeric', 'min:0'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms_conditions' => ['nullable', 'string', 'max:2000'],

            // بنود الفاتورة
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:sales_invoice_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'items.*.unit_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', 'string', 'in:fixed,percentage'],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.options' => ['nullable', 'array'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],

            // تحديث الدفعات عند تعديل الفاتورة
            'payments' => ['sometimes', 'nullable', 'array'],
            'payments.*.id' => ['nullable', 'integer', 'exists:sales_invoice_payments,id'],
            'payments.*.payment_method' => ['required_with:payments', 'string', 'in:cash,card,bank_transfer,on_account'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'gt:0'],
            'payments.*.box_id' => ['nullable', 'integer', 'exists:boxes,id'],
            'payments.*.bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'payments.*.transaction_reference' => ['nullable', 'string', 'max:100'],
            'payments.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateItemsMinimumPrices($validator);
            $this->validatePaymentsTotal($validator);
        });
    }

    protected function validateItemsMinimumPrices(Validator $validator): void
    {
        $items = $this->input('items', []);
        if (! is_array($items) || empty($items)) {
            return;
        }

        foreach ($items as $index => $item) {
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            $unitId = isset($item['unit_id']) && $item['unit_id'] !== null ? (int) $item['unit_id'] : null;
            $unitPrice = isset($item['unit_price']) && is_numeric($item['unit_price']) ? (float) $item['unit_price'] : null;

            if ($productId <= 0 || $unitPrice === null) {
                continue;
            }

            $productUnit = null;
            if ($unitId) {
                $productUnit = ProductUnit::with(['product', 'unit'])
                    ->where('product_id', $productId)
                    ->where(function ($q) use ($unitId): void {
                        $q->where('id', $unitId)->orWhere('unit_id', $unitId);
                    })
                    ->first();
            }

            if (! $productUnit) {
                $productUnit = ProductUnit::with(['product', 'unit'])
                    ->where('product_id', $productId)
                    ->first();
            }

            if ($productUnit) {
                $minAllowedPrice = (float) $productUnit->min_allowed_price;
                if ($minAllowedPrice > 0 && $unitPrice < $minAllowedPrice) {
                    $productName = $productUnit->product?->name ?? "رقم {$productId}";
                    $unitName = $productUnit->unit?->name ?? 'المحددة';
                    $validator->errors()->add(
                        "items.{$index}.unit_price",
                        "سعر الصنف [{$productName}] لوحدة [{$unitName}] ({$unitPrice}) أقل من السعر الأدنى المسموح به ({$minAllowedPrice})."
                    );
                }
            } elseif ($unitId) {
                $validator->errors()->add(
                    "items.{$index}.unit_id",
                    'الوحدة المحددة غير مرتبطة بهذا الصنف.'
                );
            }
        }
    }

    protected function validatePaymentsTotal(Validator $validator): void
    {
        if (! $this->has('payments')) {
            return;
        }

        $payments = $this->input('payments', []);
        if (! is_array($payments) || empty($payments)) {
            return;
        }

        $totalPaid = 0.0;
        foreach ($payments as $index => $payment) {
            $method = $payment['payment_method'] ?? null;
            $amount = isset($payment['amount']) && is_numeric($payment['amount']) ? (float) $payment['amount'] : 0.0;

            if ($method === 'cash' && empty($payment['box_id'])) {
                $validator->errors()->add("payments.{$index}.box_id", 'يجب تحديد الصندوق النقدي عند الدفع نقداً.');
            }

            if (in_array($method, ['card', 'bank_transfer'], true) && empty($payment['bank_account_id'])) {
                $validator->errors()->add("payments.{$index}.bank_account_id", 'يجب تحديد الحساب البنكي عند الدفع بالبطاقة أو التحويل.');
            }

            if ($amount > 0) {
                $totalPaid += $amount;
            }
        }

        $expectedGrandTotal = $this->calculateExpectedGrandTotal();
        if (round($totalPaid - $expectedGrandTotal, 2) > 0) {
            $validator->errors()->add(
                'payments',
                'مجموع الدفعات المسددة (' . round($totalPaid, 2) . ') لا يجوز أن يتجاوز إجمالي الفاتورة (' . $expectedGrandTotal . ').'
            );
        }
    }

    protected function calculateExpectedGrandTotal(): float
    {
        $invoice = $this->route('salesInvoice');
        $items = $this->input('items');

        if (! is_array($items)) {
            return $invoice ? (float) $invoice->total_amount : 0.0;
        }

        $subtotal = 0.0;
        $itemsTaxAmount = 0.0;

        foreach ($items as $item) {
            $quantity = isset($item['quantity']) && is_numeric($item['quantity']) ? (float) $item['quantity'] : 0.0;
            $unitPrice = isset($item['unit_price']) && is_numeric($item['unit_price']) ? (float) $item['unit_price'] : 0.0;
            $lineGross = round($quantity * $unitPrice, 2);

            $discountType = $item['discount_type'] ?? 'fixed';
            $discountValue = isset($item['discount_value']) && is_numeric($item['discount_value']) ? (float) $item['discount_value'] : 0.0;
            $discountAmount = $discountType === 'percentage'
                ? round($lineGross * ($discountValue / 100), 2)
                : min($lineGross, round($discountValue, 2));

            $lineSubtotal = round($lineGross - $discountAmount, 2);
            $taxRate = isset($item['tax_rate']) && is_numeric($item['tax_rate']) ? (float) $item['tax_rate'] : 0.0;
            $taxAmount = round($lineSubtotal * ($taxRate / 100), 2);

            $subtotal += $lineSubtotal;
            $itemsTaxAmount += $taxAmount;
        }

        $headerDiscountType = $this->input('discount_type', $invoice?->discount_type ?? 'fixed');
        $headerDiscountValue = $this->has('discount_value')
            ? (float) $this->input('discount_value')
            : (float) ($invoice?->discount_value ?? 0.0);

        $headerDiscountAmount = $headerDiscountType === 'percentage'
            ? round($subtotal * ($headerDiscountValue / 100), 2)
            : min($subtotal, round($headerDiscountValue, 2));

        $taxableAmount = round($subtotal - $headerDiscountAmount, 2);
        $serviceCharge = $this->has('service_charge')
            ? (float) $this->input('service_charge')
            : (float) ($invoice?->service_charge ?? 0.0);
        $deliveryFee = $this->has('delivery_fee')
            ? (float) $this->input('delivery_fee')
            : (float) ($invoice?->delivery_fee ?? 0.0);

        return max(0.0, round($taxableAmount + $itemsTaxAmount + $serviceCharge + $deliveryFee, 2));
    }

    public function messages(): array
    {
        return [
            'partner_id.required' => 'العميل مطلوب.',
            'partner_id.exists' => 'العميل المحدد غير موجود بالنظام.',
            'warehouse_id.required' => 'المستودع مطلوب لخصم وحركة البضاعة.',
            'warehouse_id.exists' => 'المستودع المحدد غير موجود بالنظام.',
            'items.required' => 'يجب إدراج صنف واحد على الأقل في الفاتورة.',
            'items.min' => 'يجب إدراج صنف واحد على الأقل في الفاتورة.',
            'items.*.product_id.required' => 'الصنف مطلوب لكل سطر في الفاتورة.',
            'items.*.product_id.exists' => 'أحد الأصناف المحددة غير موجود بالمخزون.',
            'items.*.quantity.required' => 'الكمية مطلوبة.',
            'items.*.quantity.gt' => 'يجب أن تكون الكمية أكبر من الصفر.',
            'items.*.unit_price.required' => 'سعر الوحدة مطلوب.',
            'items.*.unit_price.min' => 'لا يمكن أن يكون سعر الوحدة بالسالب.',
            'payments.*.box_id.exists' => 'الصندوق النقدي المحدد غير موجود.',
            'payments.*.bank_account_id.exists' => 'الحساب البنكي المحدد غير موجود.',
        ];
    }
}