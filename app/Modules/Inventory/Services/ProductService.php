<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductService
{
    /**
     * إنشاء صنف جديد مع الوحدات والأسعار والباركودات وقواعد إعادة الطلب
     */
    public function createProduct(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            // 1. إنشاء الصنف الأساسي
            $product = Product::create([
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'],
                'sku' => $data['sku'] ?? null,
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'inventory_policy' => $data['inventory_policy'],
                'tracking_type' => $data['tracking_type'],
                'valuation_method' => $data['valuation_method'],
                'cost_price' => $data['cost_price'] ?? 0.0000,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // 2. إنشاء الوحدات والأسعار والباركودات التابعة
            if (!empty($data['units'])) {
                $this->saveProductUnits($product, $data['units']);
            }

            // 3. إنشاء قواعد إعادة الطلب المربوطة بالمستودعات (إن وجدت)
            if (!empty($data['reorder_rules'])) {
                $this->saveReorderRules($product, $data['reorder_rules']);
            }

            return $product->load([
                'category',
                'units.unit',
                'units.prices.priceList',
                'units.barcodes',
                'reorderRules.warehouse',
            ]);
        });
    }

    /**
     * تحديث صنف قائم وإعادة بناء العلاقات التابعة مع معالجة SoftDeletes
     */
    public function updateProduct(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            // 1. تحديث بيانات الصنف الأساسية
            $product->update(array_filter([
                'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : $product->category_id,
                'name' => $data['name'] ?? $product->name,
                'sku' => array_key_exists('sku', $data) ? $data['sku'] : $product->sku,
                'description' => array_key_exists('description', $data) ? $data['description'] : $product->description,
                'type' => $data['type'] ?? $product->type,
                'inventory_policy' => $data['inventory_policy'] ?? $product->inventory_policy,
                'tracking_type' => $data['tracking_type'] ?? $product->tracking_type,
                'valuation_method' => $data['valuation_method'] ?? $product->valuation_method,
                'cost_price' => array_key_exists('cost_price', $data) ? $data['cost_price'] : $product->cost_price,
                'is_active' => array_key_exists('is_active', $data) ? $data['is_active'] : $product->is_active,
            ], fn ($value) => $value !== null));

            // 2. تحديث الوحدات والأسعار والباركودات (حذف التجميعات القديمة وإعادة البناء)
            if (isset($data['units'])) {
                foreach ($product->units as $productUnit) {
                    $productUnit->barcodes()->withTrashed()->forceDelete();
                    $productUnit->prices()->withTrashed()->forceDelete();
                    $productUnit->forceDelete();
                }
                $this->saveProductUnits($product, $data['units']);
            }

            // 3. تحديث قواعد إعادة الطلب
            if (isset($data['reorder_rules'])) {
                $product->reorderRules()->withTrashed()->forceDelete();
                $this->saveReorderRules($product, $data['reorder_rules']);
            }

            return $product->fresh([
                'category',
                'units.unit',
                'units.prices.priceList',
                'units.barcodes',
                'reorderRules.warehouse',
            ]);
        });
    }

    /**
     * حذف صنف محدد مع علاقاته الفرعية
     */
    public function deleteProduct(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $this->forceDeleteProductRelations($product);
            $product->delete();
        });
    }

    /**
     * حفظ الوحدات والأسعار والباركودات المرتبطة بالصنف
     */
    protected function saveProductUnits(Product $product, array $unitsData): void
    {
        foreach ($unitsData as $unitData) {
            $productUnit = $product->units()->create([
                'unit_id' => $unitData['unit_id'],
                'conversion_factor' => $unitData['conversion_factor'],
                'is_base_unit' => $unitData['is_base_unit'],
                'is_purchase_unit' => $unitData['is_purchase_unit'] ?? false,
                'is_sale_unit' => $unitData['is_sale_unit'] ?? false,
            ]);

            // حفظ الأسعار مع ربط product_id صراحةً
            if (!empty($unitData['prices'])) {
                foreach ($unitData['prices'] as $priceData) {
                    $productUnit->prices()->create([
                        'product_id' => $product->id,
                        'price_list_id' => $priceData['price_list_id'],
                        'price' => $priceData['price'],
                        'min_quantity' => $priceData['min_quantity'] ?? 1.0000,
                    ]);
                }
            }

            // حفظ الباركودات مع ربط product_id صراحةً
            if (!empty($unitData['barcodes'])) {
                foreach ($unitData['barcodes'] as $barcode) {
                    if (!empty($barcode)) {
                        $productUnit->barcodes()->create([
                            'product_id' => $product->id,
                            'barcode' => $barcode,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * حفظ قواعد إعادة الطلب المربوطة بالمستودعات
     */
    protected function saveReorderRules(Product $product, array $rulesData): void
    {
        foreach ($rulesData as $ruleData) {
            $product->reorderRules()->create([
                'warehouse_id' => $ruleData['warehouse_id'],
                'min_quantity' => $ruleData['min_quantity'] ?? 0.0000,
                'max_quantity' => $ruleData['max_quantity'] ?? 0.0000,
                'reorder_quantity' => $ruleData['reorder_quantity'] ?? 0.0000,
                'is_active' => $ruleData['is_active'] ?? true,
            ]);
        }
    }

    /**
     * الحذف النهائي (forceDelete) للعلاقات الفرعية لتفادي تعارض الـ Unique Constraint مع SoftDeletes
     */
    protected function forceDeleteProductRelations(Product $product): void
    {
        foreach ($product->units as $productUnit) {
            $productUnit->barcodes()->withTrashed()->forceDelete();
            $productUnit->prices()->withTrashed()->forceDelete();
            $productUnit->forceDelete();
        }

        $product->reorderRules()->withTrashed()->forceDelete();
    }
}