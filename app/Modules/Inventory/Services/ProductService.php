<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductBarcode;
use App\Modules\Inventory\Models\ProductPrice;
use App\Modules\Inventory\Models\ProductStock;
use Illuminate\Database\Eloquent\Collection;
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
                'aliases' => $data['aliases'] ?? null,
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
                'aliases' => array_key_exists('aliases', $data) ? $data['aliases'] : $product->aliases,
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
     * بحث سريع وموجه لنقاط البيع والسوبرماركت يدعم:
     * 1. مسار الباركود المباشر O(1)
     * 2. مسار باركود الميزان (Variable Weight EAN-13)
     * 3. مسار البحث النصي الفوري بالأصناف النشطة
     */
    public function fastSearch(
        string $search,
        ?int $warehouseId = null,
        ?int $priceListId = null,
        int $limit = 10
    ): Collection {
        $cleanSearch = trim($search);

        if ($cleanSearch === '') {
            return new Collection();
        }

        // المسار الأول: البحث التام عن الباركود المباشر عبر B-Tree Index
        $barcodeMatch = ProductBarcode::query()
            ->where('barcode', $cleanSearch)
            ->with([
                'product.units.unit',
                'productUnit.unit',
            ])
            ->first();

        if ($barcodeMatch && $barcodeMatch->product && $barcodeMatch->product->is_active) {
            $product = $barcodeMatch->product;
            $matchedUnit = $barcodeMatch->productUnit
                ?? $product->units->firstWhere('is_sale_unit', true)
                ?? $product->units->firstWhere('is_base_unit', true)
                ?? $product->units->first();

            $product->matched_unit = $matchedUnit;
            $product->matched_barcode = $barcodeMatch->barcode;
            $product->is_weight_barcode = false;
            $product->scanned_quantity = 1.0000;

            $this->enrichProductsWithStockAndPrice(
                new Collection([$product]),
                $warehouseId,
                $priceListId
            );

            return new Collection([$product]);
        }

        // المسار الثاني: باركود الميزان الإلكتروني (13 رقماً ويبدأ بـ 20 أو 21 أو 99)
        $isPotentialScaleBarcode = strlen($cleanSearch) === 13
            && ctype_digit($cleanSearch)
            && (str_starts_with($cleanSearch, '20') || str_starts_with($cleanSearch, '21') || str_starts_with($cleanSearch, '99'));

        if ($isPotentialScaleBarcode) {
            $itemCodeRaw = substr($cleanSearch, 2, 5);
            $itemCodeTrimmed = ltrim($itemCodeRaw, '0');
            $weightGrams = (int) substr($cleanSearch, 7, 5);
            $scannedQuantity = $weightGrams > 0 ? round($weightGrams / 1000.0, 4) : 1.0000;

            $scaleProduct = Product::query()
                ->where('is_active', true)
                ->where(function ($q) use ($itemCodeRaw, $itemCodeTrimmed) {
                    $q->where('sku', $itemCodeRaw)
                      ->orWhere('sku', $itemCodeTrimmed)
                      ->orWhereHas('barcodes', function ($b) use ($itemCodeRaw, $itemCodeTrimmed) {
                          $b->where('barcode', $itemCodeRaw)->orWhere('barcode', $itemCodeTrimmed);
                      });
                })
                ->with(['units.unit'])
                ->first();

            if ($scaleProduct) {
                $matchedUnit = $scaleProduct->units->firstWhere('is_sale_unit', true)
                    ?? $scaleProduct->units->firstWhere('is_base_unit', true)
                    ?? $scaleProduct->units->first();

                $scaleProduct->matched_unit = $matchedUnit;
                $scaleProduct->matched_barcode = $cleanSearch;
                $scaleProduct->is_weight_barcode = true;
                $scaleProduct->scanned_quantity = $scannedQuantity;

                $this->enrichProductsWithStockAndPrice(
                    new Collection([$scaleProduct]),
                    $warehouseId,
                    $priceListId
                );

                return new Collection([$scaleProduct]);
            }
        }

        // المسار الثالث: البحث النصي الخفيف بالاسم، الكود، أو الأسماء البديلة
        $products = Product::query()
            ->select(['id', 'category_id', 'name', 'sku', 'aliases', 'cost_price', 'is_active'])
            ->where('is_active', true)
            ->where(function ($q) use ($cleanSearch) {
                $q->where('name', 'like', "{$cleanSearch}%")
                  ->orWhere('sku', 'like', "{$cleanSearch}%")
                  ->orWhere('aliases', 'like', "%{$cleanSearch}%")
                  ->orWhere('name', 'like', "%{$cleanSearch}%");
            })
            ->with(['units.unit'])
            ->orderByRaw(
                "CASE WHEN name LIKE ? THEN 1 WHEN sku LIKE ? THEN 2 ELSE 3 END",
                ["{$cleanSearch}%", "{$cleanSearch}%"]
            )
            ->limit($limit)
            ->get();

        foreach ($products as $product) {
            $matchedUnit = $product->units->firstWhere('is_sale_unit', true)
                ?? $product->units->firstWhere('is_base_unit', true)
                ?? $product->units->first();

            $product->matched_unit = $matchedUnit;
            $product->matched_barcode = null;
            $product->is_weight_barcode = false;
            $product->scanned_quantity = 1.0000;
        }

        $this->enrichProductsWithStockAndPrice($products, $warehouseId, $priceListId);

        return $products;
    }

    /**
     * تزويد الأصناف بالرصيد الفعلي للمستودع المحدد وسعر البيع دفعة واحدة لمنع استعلامات N+1
     */
    protected function enrichProductsWithStockAndPrice(
        Collection $products,
        ?int $warehouseId,
        ?int $priceListId
    ): void {
        if ($products->isEmpty()) {
            return;
        }

        $productIds = $products->pluck('id')->all();

        // 1. حساب الرصيد المتاح (quantity - reserved_quantity) للمستودع المحدد
        $stockQuery = ProductStock::query()
            ->whereIn('product_id', $productIds);

        if ($warehouseId !== null) {
            $stockQuery->where('warehouse_id', $warehouseId);
        }

        $stocks = $stockQuery->groupBy('product_id')
            ->selectRaw('product_id, COALESCE(SUM(quantity - reserved_quantity), 0) as available_qty')
            ->pluck('available_qty', 'product_id');

        // 2. جلب سجلات الأسعار للأصناف المعنية دفعة واحدة
        $allPrices = ProductPrice::query()
            ->whereIn('product_id', $productIds)
            ->get();

        foreach ($products as $product) {
            $product->available_quantity = (float) ($stocks->get($product->id) ?? 0.0000);

            $unitId = $product->matched_unit?->id;
            if (!$unitId) {
                $product->resolved_price = (float) ($product->cost_price ?? 0.0000);
                continue;
            }

            $unitPrices = $allPrices
                ->where('product_id', $product->id)
                ->where('product_unit_id', $unitId);

            $priceRecord = null;
            if ($priceListId !== null) {
                $priceRecord = $unitPrices->firstWhere('price_list_id', $priceListId);
            }

            if (!$priceRecord) {
                $priceRecord = $unitPrices->first();
            }

            $product->resolved_price = $priceRecord
                ? (float) $priceRecord->price
                : (float) ($product->cost_price ?? 0.0000);
        }
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