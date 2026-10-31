<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Requests\Issues;

use App\Modules\Inventory\Enums\StockSerialStatus;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\StockSerial;
use App\Modules\Purchasing\Models\PurchaseIssue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePurchaseIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $issue = $this->route('issue');
        if (is_numeric($issue)) {
            $issue = PurchaseIssue::find($issue);
        }

        return $issue ? ($this->user()?->can('update', $issue) ?? true) : true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['sometimes', 'required', 'integer', 'exists:inventory_warehouses,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'recipient_id' => ['nullable', 'integer', 'exists:users,id'],
            'issue_date' => ['sometimes', 'required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:purchasing_issue_items,id'],
            'items.*.requisition_item_id' => ['nullable', 'integer', 'exists:purchasing_requisition_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'items.*.product_unit_id' => ['required', 'integer', 'exists:inventory_product_units,id'],
            'items.*.location_id' => ['nullable', 'integer', 'exists:inventory_warehouse_locations,id'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:inventory_stock_batches,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],

            // مصفوفة الأرقام التسلسلية وقراءات الأوزان
            'items.*.serials' => ['nullable', 'array'],
            'items.*.serials.*.serial_id' => ['required', 'integer', 'exists:inventory_serials,id'],
            'items.*.serials.*.gross_weight' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'items.*.serials.*.net_weight' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'items.*.serials.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = $this->input('items', []);
            if (! is_array($items) || empty($items)) {
                return;
            }

            /** @var PurchaseIssue|null $currentIssue */
            $currentIssue = $this->route('issue');
            if (is_numeric($currentIssue)) {
                $currentIssue = PurchaseIssue::find($currentIssue);
            }

            $warehouseId = $this->input('warehouse_id')
                ? (int) $this->input('warehouse_id')
                : ($currentIssue ? (int) $currentIssue->warehouse_id : null);

            $productIds = collect($items)->pluck('product_id')->filter()->unique()->all();
            if (empty($productIds)) {
                return;
            }

            $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

            $allSerialIds = collect($items)
                ->pluck('serials')
                ->filter()
                ->flatten(1)
                ->pluck('serial_id')
                ->filter()
                ->unique()
                ->all();

            $existingSerials = ! empty($allSerialIds)
                ? StockSerial::whereIn('id', $allSerialIds)->get()->keyBy('id')
                : collect();

            // جلب السيريالات المرتبطة مسبقاً بهذا السند لتجاوز فحص الحالة إذا كانت ضمن نفس السند الجاري تعديله
            $currentlyAttachedSerialIds = $currentIssue
                ? $currentIssue->items()->with('serials')->get()->pluck('serials')->flatten()->pluck('serial_id')->all()
                : [];

            $globallySeenSerialIds = [];

            foreach ($items as $index => $item) {
                $productId = $item['product_id'] ?? null;
                if (! $productId || ! $products->has($productId)) {
                    continue;
                }

                /** @var Product $product */
                $product = $products->get($productId);
                $trackingType = $product->tracking_type;
                $quantity = (float) ($item['quantity'] ?? 0);
                $serials = $item['serials'] ?? [];

                if ($quantity <= 0) {
                    continue;
                }

                // 1. الأصناف المتبعة بالسيريال العددي البسيط (1 قطعة = 1 سيريال)
                if ($trackingType === 'by_serial') {
                    if (! is_array($serials) || count($serials) !== (int) $quantity) {
                        $validator->errors()->add(
                            "items.{$index}.serials",
                            "الصنف '{$product->name}' يتم تتبعه بالأرقام التسلسلية؛ يجب تحديد أرقام تسلسلية بعدد مطابق تماماً للكمية المنصرفة (" . (int) $quantity . ")."
                        );
                        continue;
                    }

                    foreach ($serials as $sIndex => $serialData) {
                        $serialId = $serialData['serial_id'] ?? null;
                        if (! $serialId) {
                            $validator->errors()->add(
                                "items.{$index}.serials.{$sIndex}.serial_id",
                                "يجب تحديد الرقم التسلسلي المصروف."
                            );
                            continue;
                        }

                        if (in_array($serialId, $globallySeenSerialIds, true)) {
                            $validator->errors()->add(
                                "items.{$index}.serials.{$sIndex}.serial_id",
                                "الرقم التسلسلي مكرر في نفس إذن الصرف."
                            );
                        }
                        $globallySeenSerialIds[] = $serialId;
                    }
                }

                // 2. الأصناف المتبعة بالسيريال الوزني (أسطوانات غاز الكلور)
                if ($trackingType === 'by_weight_serial') {
                    if (! is_array($serials) || empty($serials)) {
                        $validator->errors()->add(
                            "items.{$index}.serials",
                            "الصنف '{$product->name}' يتم تتبعه بالسيريال الوزني؛ يجب إرفاق بيانات الأسطوانات المصروفة."
                        );
                        continue;
                    }

                    $fullCylindersCount = 0;
                    $emptyCylindersCount = 0;
                    $totalNetWeight = 0.0;

                    foreach ($serials as $sIndex => $serialData) {
                        $serialId = $serialData['serial_id'] ?? null;
                        if (! $serialId) {
                            $validator->errors()->add(
                                "items.{$index}.serials.{$sIndex}.serial_id",
                                "يجب اختيار الأسطوانة المصروفة."
                            );
                            continue;
                        }

                        if (in_array($serialId, $globallySeenSerialIds, true)) {
                            $validator->errors()->add(
                                "items.{$index}.serials.{$sIndex}.serial_id",
                                "الأسطوانة مكررة في نفس إذن الصرف."
                            );
                        }
                        $globallySeenSerialIds[] = $serialId;

                        /** @var StockSerial|null $serialModel */
                        $serialModel = $existingSerials->get($serialId);
                        if (! $serialModel) {
                            continue;
                        }

                        $isAlreadyAttached = in_array($serialModel->id, $currentlyAttachedSerialIds, true);

                        if ($warehouseId && $serialModel->warehouse_id && (int) $serialModel->warehouse_id !== $warehouseId && ! $isAlreadyAttached) {
                            $validator->errors()->add(
                                "items.{$index}.serials.{$sIndex}.serial_id",
                                "الأسطوانة ({$serialModel->serial_number}) غير متواجدة بالمستودع المحدد للصرف."
                            );
                        }

                        if (! $isAlreadyAttached && ! in_array($serialModel->status, [StockSerialStatus::IN_STOCK, StockSerialStatus::EMPTY], true)) {
                            $validator->errors()->add(
                                "items.{$index}.serials.{$sIndex}.serial_id",
                                "الأسطوانة ({$serialModel->serial_number}) غير متاحة للصرف (الحالة الحالية: {$serialModel->status->label()})."
                            );
                            continue;
                        }

                        // تحديد فئة الأسطوانة (فارغة أو ممتلئة)
                        $isCylinderEmpty = ($serialModel->status === StockSerialStatus::EMPTY)
                            || ((float) ($serialModel->current_gas_weight ?? 0.0) <= 0.0 && ! isset($serialData['gross_weight']));

                        if ($isCylinderEmpty) {
                            $emptyCylindersCount++;
                        } else {
                            $fullCylindersCount++;

                            $tareWeight = (float) ($serialModel->tare_weight ?? 0.0);
                            $grossWeight = isset($serialData['gross_weight']) && $serialData['gross_weight'] !== ''
                                ? (float) $serialData['gross_weight']
                                : (float) ($serialModel->gross_weight ?? 0.0);

                            if ($tareWeight > 0.0 && $grossWeight > 0.0) {
                                if ($grossWeight < $tareWeight) {
                                    $validator->errors()->add(
                                        "items.{$index}.serials.{$sIndex}.gross_weight",
                                        "الوزن الإجمالي للأسطوانة ({$grossWeight} كجم) لا يمكن أن يكون أقل من الوزن الفارغ ({$tareWeight} كجم)."
                                    );
                                } else {
                                    $netWeight = max(0.0, round($grossWeight - $tareWeight, 4));
                                    $totalNetWeight += $netWeight;
                                }
                            } else {
                                $totalNetWeight += (float) ($serialModel->current_gas_weight ?? 0.0);
                            }
                        }
                    }

                    if ($fullCylindersCount > 0 && $emptyCylindersCount > 0) {
                        $validator->errors()->add(
                            "items.{$index}.serials",
                            "لا يمكن الجمع بين أسطوانات ممتلئة وأسطوانات فارغة في نفس البند. يرجى فصل أسطوانات التشغيل عن الأسطوانات الخارجة للتعبئة."
                        );
                        continue;
                    }

                    if ($emptyCylindersCount > 0) {
                        if (count($serials) !== (int) round($quantity)) {
                            $validator->errors()->add(
                                "items.{$index}.quantity",
                                "الكمية المحددة في بند خروج الأسطوانات الفارغة للتعبئة ({$quantity}) يجب أن تطابق عدد الأسطوانات المحدد (" . count($serials) . ")."
                            );
                        }
                    }

                    if ($fullCylindersCount > 0) {
                        if (abs($totalNetWeight - $quantity) > 0.05) {
                            $validator->errors()->add(
                                "items.{$index}.quantity",
                                "الكمية المنصرفة للتشغيل ({$quantity} كجم) يجب أن تطابق مجموع صافي أوزان غاز الكلور الفعلي بالأسطوانات ({$totalNetWeight} كجم)."
                            );
                        }
                    }
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'warehouse_id' => 'المستودع',
            'department_id' => 'القسم',
            'recipient_id' => 'المستلم',
            'issue_date' => 'تاريخ الصرف',
            'notes' => 'الملاحظات',
            'items' => 'بنود الصرف',
            'items.*.id' => 'معرف البند',
            'items.*.requisition_item_id' => 'بند طلب الشراء',
            'items.*.product_id' => 'الصنف',
            'items.*.product_unit_id' => 'وحدة القياس',
            'items.*.location_id' => 'موقع التخزين / الرف',
            'items.*.batch_id' => 'رقم التشغيلة / الدفعة',
            'items.*.quantity' => 'الكمية المنصرفة',
            'items.*.unit_cost' => 'تكلفة الوحدة',
            'items.*.notes' => 'ملاحظات البند',
            'items.*.serials' => 'الأرقام التسلسلية المصروفة',
            'items.*.serials.*.serial_id' => 'معرف الأسطوانة',
            'items.*.serials.*.gross_weight' => 'الوزن الإجمالي القائم',
            'items.*.serials.*.net_weight' => 'صافي وزن الغاز',
            'items.*.serials.*.notes' => 'ملاحظات الأسطوانة',
        ];
    }
}