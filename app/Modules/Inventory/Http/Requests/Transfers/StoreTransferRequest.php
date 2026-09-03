<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\Transfers;

use App\Modules\Inventory\Models\Transfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحاً له بتنفيذ هذا الطلب
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Transfer::class);
    }

    /**
     * قواعد التحقق الخاصة بإنشاء أمر تحويل جديد
     */
    public function rules(): array
    {
        return [
            'transfer_number' => ['required', 'string', 'max:100', Rule::unique('inventory_transfers', 'transfer_number')->whereNull('deleted_at')],
            'from_warehouse_id' => ['required', 'integer', Rule::exists('inventory_warehouses', 'id')->whereNull('deleted_at')],
            'to_warehouse_id' => ['required', 'integer', 'different:from_warehouse_id', Rule::exists('inventory_warehouses', 'id')->whereNull('deleted_at')],
            'transfer_date' => ['required', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('inventory_products', 'id')->whereNull('deleted_at')],
            'items.*.batch_id' => ['nullable', 'integer', Rule::exists('inventory_stock_batches', 'id')->whereNull('deleted_at')],
            'items.*.product_unit_id' => ['required', 'integer', Rule::exists('inventory_product_units', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * مصفوفة الرسائل المخصصة للتحقق
     */
    public function messages(): array
    {
        return [
            'transfer_number.required' => 'رقم أمر التحويل مطلوب.',
            'transfer_number.unique' => 'رقم أمر التحويل مستخدم من قبل.',
            'from_warehouse_id.required' => 'المستودع المصدر مطلوب.',
            'from_warehouse_id.exists' => 'المستودع المصدر المحدد غير موجود.',
            'to_warehouse_id.required' => 'المستودع الوجهة مطلوب.',
            'to_warehouse_id.different' => 'لا يمكن التحويل إلى نفس المستودع المصدر.',
            'to_warehouse_id.exists' => 'المستودع الوجهة المحدد غير موجود.',
            'transfer_date.required' => 'تاريخ التحويل مطلوب.',
            'items.required' => 'يجب إضافة عنصر واحد على الأقل في أمر التحويل.',
            'items.min' => 'يجب إضافة عنصر واحد على الأقل في أمر التحويل.',
            'items.*.product_id.required' => 'المنتج مطلوب لكل عنصر.',
            'items.*.product_id.exists' => 'المنتج المحدد في أحد العناصر غير موجود.',
            'items.*.product_unit_id.required' => 'وحدة المنتج مطلوبة لكل عنصر.',
            'items.*.product_unit_id.exists' => 'وحدة المنتج المحددة غير موجودة.',
            'items.*.quantity.required' => 'الكمية مطلوبة لكل عنصر.',
            'items.*.quantity.gt' => 'يجب أن تكون الكمية أكبر من الصفر.',
        ];
    }
}