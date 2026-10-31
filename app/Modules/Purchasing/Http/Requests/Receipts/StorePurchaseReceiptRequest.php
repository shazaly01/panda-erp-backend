<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Requests\Receipts;

use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\StockSerial;
use App\Modules\Purchasing\Models\PurchaseReceipt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePurchaseReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PurchaseReceipt::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:partners,id'],
            'warehouse_id' => ['required', 'integer', 'exists:inventory_warehouses,id'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchasing_orders,id'],
            'receipt_date' => ['required', 'date'],
            'supplier_delivery_note' => ['nullable', 'string', 'max:100'],
            'waybill_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['array'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer', 'exists:purchasing_order_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'items.*.product_unit_id' => ['required', 'integer', 'exists:inventory_product_units,id'],
            'items.*.location_id' => ['nullable', 'integer', 'exists:inventory_warehouse_locations,id'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:inventory_stock_batches,id'],
            'items.*.quantity_received' => ['required', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'items.*.quantity_accepted' => ['required', 'numeric', 'min:0', 'lte:items.*.quantity_received', 'max:9999999999.9999'],
            'items.*.quantity_rejected' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'items.*.rejection_reason' => ['nullable', 'string', 'max:1000'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],

            // مصفوفة الأرقام التسلسلية وقراءات الأوزان
            'items.*.serials' => ['nullable', 'array'],
            'items.*.serials.*' => ['array'],
            'items.*.serials.*.serial_id' => ['nullable', 'integer', 'exists:inventory_serials,id'],
            'items.*.serials.*.serial_number' => ['nullable', 'string', 'max:100'],
            'items.*.serials.*.replaced_serial_id' => ['nullable', 'integer', 'exists:inventory_serials,id'],
            'items.*.serials.*.tare_weight' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'items.*.serials.*.gross_weight' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'items.*.serials.*.net_weight' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'items.*.serials.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $items = $this->input('items', []);
            if (! is_array($items) || $items === []) {
                return;
            }

            $productIds = collect($items)->pluck('product_id')->unique()->all();
            $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
            $allEntries = collect($items)->flatMap(fn (array $item): array => $item['serials'] ?? []);
            $allSerialIds = $allEntries->flatMap(fn (array $entry): array => [
                $entry['serial_id'] ?? null,
                $entry['replaced_serial_id'] ?? null,
            ])->filter()->unique()->all();
            $allSerialNumbers = $allEntries->pluck('serial_number')
                ->filter(fn ($number): bool => $number !== null && trim((string) $number) !== '')
                ->map(fn ($number): string => trim((string) $number))
                ->unique()->all();

            $existingSerialsById = $allSerialIds !== []
                ? StockSerial::withTrashed()->whereIn('id', $allSerialIds)->get()->keyBy('id')
                : collect();
            $existingSerialsByNumber = $allSerialNumbers !== []
                ? StockSerial::withTrashed()->whereIn('product_id', $productIds)
                    ->whereIn('serial_number', $allSerialNumbers)->get()
                    ->keyBy(fn (StockSerial $serial): string =>
                        $serial->product_id . ':' . mb_strtolower(trim((string) $serial->serial_number))
                    )
                : collect();

            $seenIdentifiers = [];
            $seenReplacementIds = [];
            $incomingSerialIds = [];

            foreach ($items as $index => $item) {
                $product = $products->get($item['product_id']);
                if (! $product) {
                    continue;
                }
                $productId = (int) $product->id;
                $trackingType = $product->tracking_type;
                $acceptedQty = (float) $item['quantity_accepted'];
                $serials = $item['serials'] ?? [];
                $isWeightSerial = $trackingType === 'by_weight_serial';
                $isStandardSerial = $trackingType === 'by_serial';

                if (! $isWeightSerial && ! $isStandardSerial) {
                    if ($serials !== []) {
                        $validator->errors()->add("items.{$index}.serials", 'لا يجوز إرفاق سيريالات لصنف لا يستخدم التتبع بالسيريال.');
                    }
                    continue;
                }

                if ($acceptedQty > 0 && $isStandardSerial) {
                    if (round($acceptedQty, 4) !== (float) (int) $acceptedQty) {
                        $validator->errors()->add("items.{$index}.quantity_accepted", 'الكمية المقبولة للصنف المتتبع بالسيريال العددي يجب أن تكون عدداً صحيحاً دون كسور.');
                    } elseif (count($serials) !== (int) $acceptedQty) {
                        $validator->errors()->add(
                            "items.{$index}.serials",
                            "الصنف '{$product->name}' يتطلب رقماً تسلسلياً لكل وحدة من الكمية المقبولة (" . (int) $acceptedQty . ').'
                        );
                    }
                }

                if ($acceptedQty > 0 && $isWeightSerial && $serials === []) {
                    $validator->errors()->add(
                        "items.{$index}.serials",
                        "الصنف '{$product->name}' يتم تتبعه بالسيريال الوزني؛ يجب إرفاق بيانات الأسطوانات وقراءات الميزان."
                    );
                    continue;
                }

                $totalNetWeight = 0.0;
                foreach ($serials as $sIndex => $serialData) {
                    $path = "items.{$index}.serials.{$sIndex}";
                    $serialId = isset($serialData['serial_id']) ? (int) $serialData['serial_id'] : null;
                    $serialNumber = isset($serialData['serial_number']) ? trim((string) $serialData['serial_number']) : '';
                    $replacedSerialId = isset($serialData['replaced_serial_id']) ? (int) $serialData['replaced_serial_id'] : null;

                    if ($serialId === null && $serialNumber === '') {
                        $validator->errors()->add("{$path}.serial_number", 'يجب تحديد معرف السيريال المسجل أو إدخال الرقم التسلسلي.');
                        continue;
                    }

                    $serialModel = $serialId !== null
                        ? $existingSerialsById->get($serialId)
                        : $existingSerialsByNumber->get($productId . ':' . mb_strtolower($serialNumber));

                    if ($serialId !== null && (! $serialModel || (int) $serialModel->product_id !== $productId)) {
                        $validator->errors()->add("{$path}.serial_id", 'معرف الأسطوانة غير صالح لهذا الصنف.');
                        continue;
                    }
                    if ($serialModel && $serialModel->trashed()) {
                        $validator->errors()->add("{$path}.serial_number", 'السيريال مسجل كمحذوف؛ يجب مراجعة سجله قبل إعادة استخدام رقمه.');
                        continue;
                    }
                    if ($serialId !== null && $serialNumber !== '' &&
                        mb_strtolower(trim((string) $serialModel->serial_number)) !== mb_strtolower($serialNumber)) {
                        $validator->errors()->add("{$path}.serial_number", 'رقم الأسطوانة لا يطابق معرفها؛ أعد اختيار الأسطوانة أو صحح الرقم.');
                        continue;
                    }

                    $identifier = $serialModel
                        ? 'id_' . $serialModel->id
                        : 'product_' . $productId . ':num_' . mb_strtolower($serialNumber);
                    if (isset($seenIdentifiers[$identifier])) {
                        $validator->errors()->add("{$path}.serial_number", 'الأسطوانة أو السيريال مكرر داخل سند الاستلام.');
                    }
                    $seenIdentifiers[$identifier] = true;
                    if ($serialModel) {
                        $incomingSerialIds[(int) $serialModel->id] = true;
                    }

                    if ($replacedSerialId !== null) {
                        $replacedSerial = $existingSerialsById->get($replacedSerialId);
                        if (! $replacedSerial || $replacedSerial->trashed() || (int) $replacedSerial->product_id !== $productId) {
                            $validator->errors()->add("{$path}.replaced_serial_id", 'الأسطوانة المحددة للاستبدال غير صالحة لهذا الصنف.');
                        } elseif ($serialModel && (int) $serialModel->id === $replacedSerialId) {
                            $validator->errors()->add("{$path}.replaced_serial_id", 'لا يمكن أن تكون الأسطوانة المستبدلة هي الأسطوانة الواردة نفسها.');
                        } elseif (isset($seenReplacementIds[$replacedSerialId])) {
                            $validator->errors()->add("{$path}.replaced_serial_id", 'لا يجوز استبدال الأسطوانة القديمة نفسها بأكثر من أسطوانة في السند.');
                        } else {
                            $seenReplacementIds[$replacedSerialId] = "{$path}.replaced_serial_id";
                        }
                    }

                    if (! $isWeightSerial) {
                        continue;
                    }

                    $tareWeight = isset($serialData['tare_weight']) && $serialData['tare_weight'] !== ''
                        ? (float) $serialData['tare_weight']
                        : ($serialModel && $serialModel->tare_weight !== null ? (float) $serialModel->tare_weight : null);
                    $grossWeight = isset($serialData['gross_weight']) && $serialData['gross_weight'] !== ''
                        ? (float) $serialData['gross_weight']
                        : null;

                    if ($acceptedQty > 0) {
                        if ($tareWeight === null || $tareWeight <= 0.0) {
                            $validator->errors()->add("{$path}.tare_weight", 'الوزن الفارغ للأسطوانة مطلوب ويجب أن يكون أكبر من الصفر.');
                        }
                        if ($grossWeight === null || $grossWeight <= 0.0) {
                            $validator->errors()->add("{$path}.gross_weight", 'الوزن الإجمالي على الميزان مطلوب ويجب أن يكون أكبر من الصفر.');
                        }
                    }

                    if ($tareWeight !== null && $grossWeight !== null) {
                        if ($grossWeight < $tareWeight || ($acceptedQty > 0 && $grossWeight === $tareWeight)) {
                            $validator->errors()->add("{$path}.gross_weight", 'الوزن الإجمالي لا يصح مقارنة بالوزن الفارغ؛ وللغاز المقبول يجب أن يكون أكبر منه.');
                        } else {
                            $totalNetWeight = round($totalNetWeight + round($grossWeight - $tareWeight, 4), 4);
                        }
                    }
                }

                // رفع هامش السماحية إلى 1.0 كجم لمراعاة فروق موازين المحطة والبسكول
                if ($isWeightSerial && $acceptedQty > 0 && abs($totalNetWeight - $acceptedQty) > 1.0) {
                    $validator->errors()->add(
                        "items.{$index}.quantity_accepted",
                        "الكمية المقبولة ({$acceptedQty} كجم) يجب أن تطابق مجموع صافي أوزان غاز الأسطوانات ({$totalNetWeight} كجم) بهامش سماحية ميزان معقول."
                    );
                }
            }

            foreach ($seenReplacementIds as $replacementId => $path) {
                if (isset($incomingSerialIds[$replacementId])) {
                    $validator->errors()->add($path, 'لا يجوز تسجيل الأسطوانة نفسها كواردة وكأسطوانة مستبدلة في السند.');
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => 'المورد',
            'warehouse_id' => 'المستودع',
            'purchase_order_id' => 'أمر الشراء',
            'receipt_date' => 'تاريخ الاستلام',
            'supplier_delivery_note' => 'إذن تسليم المورد',
            'waybill_number' => 'بوليصة الشحن',
            'notes' => 'الملاحظات',
            'items' => 'بنود الاستلام',
            'items.*.purchase_order_item_id' => 'بند أمر الشراء',
            'items.*.product_id' => 'المنتج',
            'items.*.product_unit_id' => 'وحدة القياس',
            'items.*.location_id' => 'موقع التخزين/الرف',
            'items.*.batch_id' => 'رقم التشغيلة/الدفعة',
            'items.*.quantity_received' => 'الكمية المستلمة',
            'items.*.quantity_accepted' => 'الكمية المقبولة',
            'items.*.quantity_rejected' => 'الكمية المرفوضة',
            'items.*.unit_cost' => 'تكلفة الوحدة',
            'items.*.rejection_reason' => 'سبب الرفض',
            'items.*.notes' => 'ملاحظات البند',
            'items.*.serials' => 'الأرقام التسلسلية',
            'items.*.serials.*.serial_id' => 'معرف السيريال',
            'items.*.serials.*.serial_number' => 'الرقم التسلسلي للأسطوانة',
            'items.*.serials.*.replaced_serial_id' => 'الأسطوانة المستبدلة',
            'items.*.serials.*.tare_weight' => 'الوزن الفارغ',
            'items.*.serials.*.gross_weight' => 'الوزن الإجمالي القائم',
            'items.*.serials.*.net_weight' => 'صافي وزن الغاز',
            'items.*.serials.*.notes' => 'ملاحظات السيريال',
        ];
    }
}