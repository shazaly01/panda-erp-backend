<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Enums\StockSerialStatus;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductBarcode;
use App\Modules\Inventory\Models\ProductPrice;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\ProductUnit;
use App\Modules\Inventory\Models\StockSerial;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class ProductService
{
    /**
     * إنشاء صنف جديد مع الوحدات والأسعار والباركودات وقواعد إعادة الطلب
     */
    public function createProduct(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
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

            if (! empty($data['units'])) {
                $this->saveProductUnits($product, $data['units']);
            }

            if (! empty($data['reorder_rules'])) {
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
     * تحديث صنف قائم مع مزامنة ذكية للوحدات والأسعار لتفادي كسر القيود الأجنبية
     */
    public function updateProduct(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
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

            // 2. مزامنة الوحدات القائمة وتحديثها في مكانها دون حذف عشوائي
            if (isset($data['units'])) {
                $this->syncProductUnits($product, $data['units']);
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
        DB::transaction(function () use ($product): void {
            $this->forceDeleteProductRelations($product);
            $product->delete();
        });
    }

    /**
     * مزامنة الوحدات وتحديثها بدقة (تعديل القائم وإنشاء الجديد وحذف المستبعد غير المرتبط)
     */
    protected function syncProductUnits(Product $product, array $unitsData): void
    {
        $uniqueSubmitted = collect($unitsData)->unique('unit_id')->values();
        $submittedUnitIds = $uniqueSubmitted->pluck('unit_id')->map(fn ($id) => (int) $id)->all();

        /** @var \Illuminate\Database\Eloquent\Collection<int, ProductUnit> $existingProductUnits */
        $existingProductUnits = $product->units()->get()->keyBy('unit_id');

        // حذف الوحدات التي استبعدها المستخدم من النموذج بشرط عدم ارتباطها بحركات
        foreach ($existingProductUnits as $unitId => $existingUnit) {
            if (! in_array((int) $unitId, $submittedUnitIds, true)) {
                $existingUnit->barcodes()->delete();
                $existingUnit->prices()->delete();
                $existingUnit->delete();
            }
        }

        // تحديث الوحدات القائمة أو إنشاء وحدات جديدة
        foreach ($uniqueSubmitted as $unitData) {
            $unitId = (int) $unitData['unit_id'];

            /** @var ProductUnit|null $productUnit */
            $productUnit = $existingProductUnits->get($unitId);

            if ($productUnit) {
                // تحديث الوحدة القائمة في مكانها للحفاظ على معرّفها وعلاقاتها في سندات الاستلام
                $productUnit->update([
                    'conversion_factor' => $unitData['conversion_factor'],
                    'is_base_unit' => $unitData['is_base_unit'],
                    'is_purchase_unit' => $unitData['is_purchase_unit'] ?? false,
                    'is_sale_unit' => $unitData['is_sale_unit'] ?? false,
                ]);
            } else {
                // إنشاء وحدة جديدة لم تكن مسجلة مسبقاً للصنف
                $productUnit = $product->units()->create([
                    'unit_id' => $unitId,
                    'conversion_factor' => $unitData['conversion_factor'],
                    'is_base_unit' => $unitData['is_base_unit'],
                    'is_purchase_unit' => $unitData['is_purchase_unit'] ?? false,
                    'is_sale_unit' => $unitData['is_sale_unit'] ?? false,
                ]);
            }

            // مزامنة الأسعار التابعة للوحدة
            if (isset($unitData['prices'])) {
                $productUnit->prices()->delete();
                foreach ($unitData['prices'] as $priceData) {
                    $productUnit->prices()->create([
                        'product_id' => $product->id,
                        'price_list_id' => $priceData['price_list_id'],
                        'price' => $priceData['price'],
                        'min_quantity' => $priceData['min_quantity'] ?? 1.0000,
                    ]);
                }
            }

            // مزامنة الباركودات التابعة للوحدة
            if (isset($unitData['barcodes'])) {
                $productUnit->barcodes()->delete();
                foreach ($unitData['barcodes'] as $barcode) {
                    if (! empty($barcode)) {
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
     * بحث سريع وفوري يدعم باركود السيريال، باركود الصنف، والبحث النصي المباشر
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

        // المسار الأول: البحث المباشر برقم السيريال الصريح لأسطوانات الغاز
        $serialMatch = StockSerial::query()
            ->where('serial_number', $cleanSearch)
            ->with([
                'product.units.unit',
                'warehouse',
                'location',
            ])
            ->first();

        if ($serialMatch && $serialMatch->product && $serialMatch->product->is_active) {
            $product = $serialMatch->product;
            $matchedUnit = $product->units->firstWhere('is_base_unit', true)
                ?? $product->units->firstWhere('is_sale_unit', true)
                ?? $product->units->first();

            $isWeightSerial = ($product->tracking_type === 'by_weight_serial');
            $gasWeight = (float) ($serialMatch->current_gas_weight ?? 0.0);

            $product->matched_unit = $matchedUnit;
            $product->matched_barcode = null;
            $product->matched_serial = $serialMatch;
            $product->is_serial_barcode = true;
            $product->is_weight_barcode = false;
            $product->scanned_quantity = $isWeightSerial ? $gasWeight : 1.0000;

            $this->enrichProductsWithStockAndPrice(
                new Collection([$product]),
                $warehouseId,
                $priceListId
            );

            return new Collection([$product]);
        }

        // المسار الثاني: البحث التام عن الباركود المباشر عبر الفهرس
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
            $product->matched_serial = null;
            $product->is_serial_barcode = false;
            $product->is_weight_barcode = false;
            $product->scanned_quantity = 1.0000;

            $this->enrichProductsWithStockAndPrice(
                new Collection([$product]),
                $warehouseId,
                $priceListId
            );

            return new Collection([$product]);
        }

        // المسار الثالث: البحث النصي في الاسم والرمز SKU والأسماء البديلة
        $products = Product::query()
            ->select([
                'id',
                'category_id',
                'name',
                'sku',
                'aliases',
                'tracking_type',
                'cost_price',
                'is_active',
            ])
            ->where('is_active', true)
            ->where(function ($q) use ($cleanSearch): void {
                $q->where('name', 'like', "{$cleanSearch}%")
                    ->orWhere('sku', 'like', "{$cleanSearch}%")
                    ->orWhere('aliases', 'like', "%{$cleanSearch}%")
                    ->orWhere('name', 'like', "%{$cleanSearch}%");
            })
            ->with(['units.unit'])
            ->orderByRaw(
                'CASE WHEN name LIKE ? THEN 1 WHEN sku LIKE ? THEN 2 ELSE 3 END',
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
            $product->matched_serial = null;
            $product->is_serial_barcode = false;
            $product->is_weight_barcode = false;
            $product->scanned_quantity = 1.0000;
        }

        $this->enrichProductsWithStockAndPrice($products, $warehouseId, $priceListId);

        return $products;
    }

    /**
     * إلحاق كميات واضحة بالأصناف دون حفظها في قاعدة البيانات.
     *
     * available_quantity يبقى الرصيد المتاح بوحدة الصنف الأساسية، كما كان.
     * للأصناف by_weight_serial فقط:
     * gas_stock_kg: رصيد دفتر المخزون قبل طرح المحجوز.
     * gas_reserved_kg: المحجوز من دفتر المخزون.
     * gas_available_kg: رصيد دفتر المخزون بعد طرح المحجوز.
     * cylinders_count / cylinders_by_status: عدد سجلات الأسطوانات بالمواقع الداخلية.
     *
     * quantity_warehouse_id يحدد المخزن، أو يكون null عند جمع المخازن كلها.
     * هذه البيانات ليست جرداً فعلياً ولا إثباتاً لملكية الأسطوانات أو رصيد المورد.
     * يمكن إعادة استخدام الدالة لاحقاً لقائمة الأصناف دون تكرار منطق الكميات.
     * تقبل مجموعة نماذج الأصناف العامة أو Eloquent؛ فهذا هو نوع getCollection
     * المعلن في Paginator، ولا نستخدم هنا دوال خاصة بمجموعة Eloquent.
     */
    public function enrichInventoryQuantities(SupportCollection $products, ?int $warehouseId = null): SupportCollection
    {
        if ($products->isEmpty()) {
            return $products;
        }

        $productIds = $products->pluck('id')->all();
        $stockQuery = ProductStock::query()->whereIn('product_id', $productIds);
        if ($warehouseId !== null) {
            $stockQuery->where('warehouse_id', $warehouseId);
        }

        // نفس تعبير الرصيد المتاح القديم، مع إظهار الإجمالي والمحجوز بشكل مستقل.
        // لا نخفي الأرصدة السالبة القديمة بتحويلها إلى صفر.
        $stocks = $stockQuery->groupBy('product_id')
            ->selectRaw('product_id, COALESCE(SUM(quantity), 0) as stock_qty, COALESCE(SUM(reserved_quantity), 0) as reserved_qty, COALESCE(SUM(quantity - reserved_quantity), 0) as available_qty')
            ->get()
            ->keyBy('product_id');

        $cylinderCounts = $this->buildCylinderCounts($products, $warehouseId);

        foreach ($products as $product) {
            $stock = $stocks->get($product->id);
            $product->available_quantity = (float) ($stock?->available_qty ?? 0.0000);
            $product->quantity_warehouse_id = $warehouseId;

            if ($product->tracking_type === 'by_weight_serial') {
                $counts = $cylinderCounts[(int) $product->id];
                $product->gas_stock_kg = (float) ($stock?->stock_qty ?? 0.0000);
                $product->gas_reserved_kg = (float) ($stock?->reserved_qty ?? 0.0000);
                $product->gas_available_kg = $product->available_quantity;
                $product->cylinders_by_status = $counts;
                $product->cylinders_count = array_sum($counts);
            } else {
                // null تعني أن حقول الغاز والأسطوانات لا تنطبق على هذا الصنف.
                $product->gas_stock_kg = null;
                $product->gas_reserved_kg = null;
                $product->gas_available_kg = null;
                $product->cylinders_by_status = null;
                $product->cylinders_count = null;
            }
        }

        return $products;
    }

    /**
     * عد الأسطوانات من السجلات الفعلية، لا من أوزان الغاز أو عدد سطور المستندات.
     * لا نعد WITH_VENDOR أو REPLACED أو SCRAPPED، حتى لو حمل سجل قديم مخزناً.
     * لا نعد سجلاً محذوفاً منطقياً أو سجلاً بلا مخزن مرتبط.
     */
    protected function buildCylinderCounts(SupportCollection $products, ?int $warehouseId): array
    {
        $weightProductIds = $products
            ->filter(fn ($product): bool => $product->tracking_type === 'by_weight_serial')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($weightProductIds === []) {
            return [];
        }

        $emptyCounts = [
            StockSerialStatus::IN_STOCK->value => 0,
            StockSerialStatus::IN_SERVICE->value => 0,
            StockSerialStatus::EMPTY->value => 0,
            StockSerialStatus::IN_MAINTENANCE->value => 0,
        ];
        $countsByProduct = [];
        foreach ($weightProductIds as $productId) {
            $countsByProduct[$productId] = $emptyCounts;
        }

        // استعلام مجمع لجميع أصناف الغاز في النتائج، بدلاً من استعلام لكل صنف.
        $serialQuery = StockSerial::query()
            ->whereIn('product_id', $weightProductIds)
            ->whereNotNull('warehouse_id')
            ->whereIn('status', array_keys($emptyCounts));
        if ($warehouseId !== null) {
            $serialQuery->where('warehouse_id', $warehouseId);
        }

        $rows = $serialQuery->selectRaw('product_id, status, COUNT(*) as cylinder_count')
            ->groupBy('product_id', 'status')
            ->get();

        foreach ($rows as $row) {
            $status = $row->status instanceof StockSerialStatus
                ? $row->status->value
                : (string) $row->status;
            $countsByProduct[(int) $row->product_id][$status] = (int) $row->cylinder_count;
        }

        return $countsByProduct;
    }

    /**
     * تزويد الأصناف بالكميات المنفصلة وسعر البيع، مع إبقاء السلوك السابق للأسعار.
     */
    protected function enrichProductsWithStockAndPrice(
        Collection $products,
        ?int $warehouseId,
        ?int $priceListId
    ): void {
        if ($products->isEmpty()) {
            return;
        }

        $this->enrichInventoryQuantities($products, $warehouseId);
        $productIds = $products->pluck('id')->all();
        $allPrices = ProductPrice::query()
            ->whereIn('product_id', $productIds)
            ->get();

        foreach ($products as $product) {
            $unitId = $product->matched_unit?->id;
            if (! $unitId) {
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

            if (! $priceRecord) {
                $priceRecord = $unitPrices->first();
            }

            $product->resolved_price = $priceRecord
                ? (float) $priceRecord->price
                : (float) ($product->cost_price ?? 0.0000);
        }
    }

    /**
     * حفظ الوحدات والأسعار والباركودات عند إنشاء صنف جديد لأول مرة
     */
    protected function saveProductUnits(Product $product, array $unitsData): void
    {
        $uniqueUnits = collect($unitsData)->unique('unit_id')->values()->all();

        foreach ($uniqueUnits as $unitData) {
            $productUnit = $product->units()->create([
                'unit_id' => $unitData['unit_id'],
                'conversion_factor' => $unitData['conversion_factor'],
                'is_base_unit' => $unitData['is_base_unit'],
                'is_purchase_unit' => $unitData['is_purchase_unit'] ?? false,
                'is_sale_unit' => $unitData['is_sale_unit'] ?? false,
            ]);

            if (! empty($unitData['prices'])) {
                foreach ($unitData['prices'] as $priceData) {
                    $productUnit->prices()->create([
                        'product_id' => $product->id,
                        'price_list_id' => $priceData['price_list_id'],
                        'price' => $priceData['price'],
                        'min_quantity' => $priceData['min_quantity'] ?? 1.0000,
                    ]);
                }
            }

            if (! empty($unitData['barcodes'])) {
                foreach ($unitData['barcodes'] as $barcode) {
                    if (! empty($barcode)) {
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
     * الحذف النهائي للعلاقات الفرعية للصنف
     */
    protected function forceDeleteProductRelations(Product $product): void
    {
        foreach ($product->units as $productUnit) {
            $productUnit->barcodes()->withTrashed()->forceDelete();
            $productUnit->prices()->withTrashed()->forceDelete();
            $productUnit->delete();
        }

        $product->reorderRules()->withTrashed()->forceDelete();
    }
}
