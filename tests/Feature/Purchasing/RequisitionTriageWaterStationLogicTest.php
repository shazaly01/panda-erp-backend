<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use Tests\ApiTestCase;
use App\Modules\Purchasing\Services\PurchaseRequisitionService;
use App\Modules\Purchasing\Services\PurchaseIssueService;
use App\Modules\Purchasing\Models\PurchaseRequisition;
use App\Modules\Purchasing\Models\PurchaseRequisitionItem;
use App\Modules\Purchasing\Models\PurchaseIssue;
use App\Modules\Purchasing\Enums\RequisitionStatus;
use App\Modules\Purchasing\Enums\RequisitionPriority;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductUnit;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\Transfer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use InvalidArgumentException;

class RequisitionTriageWaterStationLogicTest extends ApiTestCase
{
    protected PurchaseRequisitionService $requisitionService;
    protected Warehouse $mainWarehouse;
    protected Warehouse $chlorineOperatingWarehouse;
    protected Unit $baseUnit;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. استدعاء الخدمة من الـ Container
        $this->requisitionService = app(PurchaseRequisitionService::class);

        // 2. إنشاء مستودع رئيسي (المصدر) ومستودع تشغيل الكلور (الوجهة التشغيلية النشطة)
        $this->mainWarehouse = Warehouse::create([
            'code'         => 'WH-MAIN',
            'name'         => 'المخزن الرئيسي للمحطة',
            'is_operating' => false,
            'is_active'    => true,
        ]);

        $this->chlorineOperatingWarehouse = Warehouse::create([
            'code'         => 'WH-CHLORINE',
            'name'         => 'مخزن ومحطة حقن الكلور التشغيلية',
            'is_operating' => true,
            'is_active'    => true,
        ]);

        // 3. تجهيز وحدة القياس الأساسية
        $this->baseUnit = $this->createMeasurementUnit('كيلوجرام', 'KG');
    }

    #[Test]
    public function chlorine_item_with_sufficient_stock_suggests_transfer_to_operating_warehouse(): void
    {
        [$chlorineProduct, $chlorineUnit] = $this->createProduct('أسطوانة غاز كلور 50 كجم', 'by_weight_serial', 150.0);
        $this->setStock($this->mainWarehouse->id, $chlorineProduct->id, 60.0);

        $requisition = $this->createApprovedRequisition($chlorineProduct, $chlorineUnit, 20.0);

        $overview = $this->requisitionService->getTriageOverview($requisition, $this->mainWarehouse->id);

        $this->assertCount(1, $overview['items']);
        $itemOverview = $overview['items'][0];

        $this->assertTrue($itemOverview['is_chlorine'], 'يجب التعرف على الصنف ككلور/غاز بالوزن والسيريال.');
        $this->assertTrue($itemOverview['can_fulfill_from_stock'], 'يجب تأكيد إمكانية توفير كامل الكمية من المخزون.');
        $this->assertFalse($itemOverview['can_fulfill_partially']);
        $this->assertEquals('transfer', $itemOverview['suggested_action'], 'يجب أن يكون الإجراء المقترح للكلور هو تحويل مخازن.');
        $this->assertEquals($this->chlorineOperatingWarehouse->id, $itemOverview['suggested_to_warehouse_id']);
        $this->assertEquals(60.0, (float) $itemOverview['available_stock']);
        $this->assertEquals(20.0, (float) $itemOverview['fulfillable_quantity']);
        $this->assertEquals(0.0, (float) $itemOverview['shortage_quantity']);
    }

    #[Test]
    public function executing_triage_for_chlorine_creates_inventory_transfer_and_increments_transferred_quantity(): void
    {
        [$chlorineProduct, $chlorineUnit] = $this->createProduct('أسطوانة غاز كلور 65 كجم', 'by_weight_serial', 200.0);
        $this->setStock($this->mainWarehouse->id, $chlorineProduct->id, 100.0);

        $requisition = $this->createApprovedRequisition($chlorineProduct, $chlorineUnit, 30.0);
        /** @var PurchaseRequisitionItem $requisitionItem */
        $requisitionItem = $requisition->items->first();

        $triagePayload = [
            'warehouse_id' => $this->mainWarehouse->id,
            'items' => [
                [
                    'requisition_item_id' => $requisitionItem->id,
                    'action'              => 'transfer',
                    'quantity'            => 30.0,
                    'to_warehouse_id'     => $this->chlorineOperatingWarehouse->id,
                ],
            ],
        ];

        $result = $this->requisitionService->executeTriage($requisition, $triagePayload, $this->adminUser->id);

        $this->assertInstanceOf(Transfer::class, $result['draft_transfer']);
        $this->assertNull($result['draft_issue']);

        /** @var Transfer $transfer */
        $transfer = $result['draft_transfer'];
        $this->assertEquals($requisition->id, $transfer->requisition_id);
        $this->assertEquals($this->mainWarehouse->id, $transfer->from_warehouse_id);
        $this->assertEquals($this->chlorineOperatingWarehouse->id, $transfer->to_warehouse_id);
        $this->assertEquals('pending', $transfer->status);

        $this->assertCount(1, $transfer->items);
        $transferItem = $transfer->items->first();
        $this->assertEquals($chlorineProduct->id, $transferItem->product_id);
        $this->assertEquals(30.0, (float) $transferItem->quantity);

        $requisitionItem->refresh();
        $this->assertEquals(30.0, (float) $requisitionItem->quantity_transferred);
        $this->assertEquals(0.0, (float) $requisitionItem->quantity_issued);
        $this->assertEquals(0.0, (float) $requisitionItem->remaining_quantity);

        $this->assertDatabaseHas('inventory_transfers', [
            'id'             => $transfer->id,
            'requisition_id' => $requisition->id,
        ]);
        $this->assertDatabaseMissing('purchasing_issues', [
            'requisition_id' => $requisition->id,
        ]);
    }

    #[Test]
    public function chlorine_item_with_insufficient_stock_suggests_purchase(): void
    {
        [$chlorineProduct, $chlorineUnit] = $this->createProduct('أسطوانة غاز كلور 50 كجم', 'by_weight_serial', 150.0);
        $this->setStock($this->mainWarehouse->id, $chlorineProduct->id, 0.0);

        $requisition = $this->createApprovedRequisition($chlorineProduct, $chlorineUnit, 25.0);

        $overview = $this->requisitionService->getTriageOverview($requisition, $this->mainWarehouse->id);
        $itemOverview = $overview['items'][0];

        $this->assertTrue($itemOverview['is_chlorine']);
        $this->assertFalse($itemOverview['can_fulfill_from_stock']);
        $this->assertFalse($itemOverview['can_fulfill_partially']);
        $this->assertEquals('purchase', $itemOverview['suggested_action']);
        $this->assertEquals(0.0, (float) $itemOverview['available_stock']);
        $this->assertEquals(0.0, (float) $itemOverview['fulfillable_quantity']);
        $this->assertEquals(25.0, (float) $itemOverview['shortage_quantity']);
    }

    #[Test]
    public function standard_item_with_sufficient_stock_suggests_issue_and_not_transfer(): void
    {
        [$regularProduct, $regularUnit] = $this->createProduct('شبة معالجة المياه', 'none', 45.0);
        $this->setStock($this->mainWarehouse->id, $regularProduct->id, 80.0);

        $requisition = $this->createApprovedRequisition($regularProduct, $regularUnit, 15.0);

        $overview = $this->requisitionService->getTriageOverview($requisition, $this->mainWarehouse->id);
        $itemOverview = $overview['items'][0];

        $this->assertFalse($itemOverview['is_chlorine']);
        $this->assertTrue($itemOverview['can_fulfill_from_stock']);
        $this->assertFalse($itemOverview['can_fulfill_partially']);
        $this->assertEquals('issue', $itemOverview['suggested_action']);
        $this->assertNull($itemOverview['suggested_to_warehouse_id']);
        $this->assertEquals(15.0, (float) $itemOverview['fulfillable_quantity']);
        $this->assertEquals(0.0, (float) $itemOverview['shortage_quantity']);
    }

    #[Test]
    public function executing_triage_for_standard_item_creates_purchase_issue_and_not_transfer(): void
    {
        [$regularProduct, $regularUnit] = $this->createProduct('محبس مياه قطري 2 بوصة', 'none', 90.0);
        $this->setStock($this->mainWarehouse->id, $regularProduct->id, 20.0);

        $requisition = $this->createApprovedRequisition($regularProduct, $regularUnit, 5.0);
        /** @var PurchaseRequisitionItem $requisitionItem */
        $requisitionItem = $requisition->items->first();

        $triagePayload = [
            'warehouse_id' => $this->mainWarehouse->id,
            'items' => [
                [
                    'requisition_item_id' => $requisitionItem->id,
                    'action'              => 'issue',
                    'quantity'            => 5.0,
                ],
            ],
        ];

        $result = $this->requisitionService->executeTriage($requisition, $triagePayload, $this->adminUser->id);

        $this->assertInstanceOf(PurchaseIssue::class, $result['draft_issue']);
        $this->assertNull($result['draft_transfer']);

        /** @var PurchaseIssue $issue */
        $issue = $result['draft_issue'];
        $this->assertEquals($requisition->id, $issue->requisition_id);
        $this->assertEquals($this->mainWarehouse->id, $issue->warehouse_id);

        $requisitionItem->refresh();
        $this->assertEquals(0.0, (float) $requisitionItem->quantity_transferred);
        $this->assertEquals(0.0, (float) $requisitionItem->quantity_issued);
        $this->assertEquals(5.0, (float) $requisitionItem->remaining_quantity);

        app(PurchaseIssueService::class)->confirmIssue($issue, $this->adminUser->id);

        $requisitionItem->refresh();
        $this->assertEquals(5.0, (float) $requisitionItem->quantity_issued);
        $this->assertEquals(0.0, (float) $requisitionItem->remaining_quantity);

        $this->assertDatabaseHas('purchasing_issues', [
            'id'             => $issue->id,
            'requisition_id' => $requisition->id,
        ]);
        $this->assertDatabaseMissing('inventory_transfers', [
            'requisition_id' => $requisition->id,
        ]);
    }

    #[Test]
    public function standard_item_with_insufficient_stock_suggests_purchase(): void
    {
        [$regularProduct, $regularUnit] = $this->createProduct('فلتر كربوني 10 ميكرون', 'none', 30.0);
        $this->setStock($this->mainWarehouse->id, $regularProduct->id, 0.0);

        $requisition = $this->createApprovedRequisition($regularProduct, $regularUnit, 10.0);

        $overview = $this->requisitionService->getTriageOverview($requisition, $this->mainWarehouse->id);
        $itemOverview = $overview['items'][0];

        $this->assertFalse($itemOverview['is_chlorine']);
        $this->assertFalse($itemOverview['can_fulfill_from_stock']);
        $this->assertFalse($itemOverview['can_fulfill_partially']);
        $this->assertEquals('purchase', $itemOverview['suggested_action']);
        $this->assertEquals(0.0, (float) $itemOverview['available_stock']);
        $this->assertEquals(0.0, (float) $itemOverview['fulfillable_quantity']);
        $this->assertEquals(10.0, (float) $itemOverview['shortage_quantity']);
    }

    #[Test]
    public function triage_execution_fails_if_requisition_is_not_approved(): void
    {
        [$chlorineProduct, $chlorineUnit] = $this->createProduct('أسطوانة كلور مسودة', 'by_weight_serial', 150.0);
        $this->setStock($this->mainWarehouse->id, $chlorineProduct->id, 50.0);

        $requisition = PurchaseRequisition::create([
            'requisition_number' => 'PR-DRAFT-' . uniqid(),
            'requested_by'       => $this->adminUser->id,
            'request_date'       => Carbon::now()->toDateString(),
            'priority'           => RequisitionPriority::MEDIUM,
            'status'             => RequisitionStatus::DRAFT,
            'created_by'         => $this->adminUser->id,
            'updated_by'         => $this->adminUser->id,
        ]);

        $item = $requisition->items()->create([
            'product_id'          => $chlorineProduct->id,
            'item_name'           => $chlorineProduct->name,
            'product_unit_id'     => $chlorineUnit->id,
            'quantity_requested'  => 10.0,
            'quantity_approved'   => 0.0,
            'quantity_ordered'    => 0.0,
            'quantity_issued'     => 0.0,
            'quantity_transferred'=> 0.0,
            'estimated_unit_cost' => 150.0,
            'created_by'          => $this->adminUser->id,
            'updated_by'          => $this->adminUser->id,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('لا يمكن فرز وتوزيع الطلب إلا إذا كان معتمداً.');

        $this->requisitionService->executeTriage($requisition, [
            'warehouse_id' => $this->mainWarehouse->id,
            'items' => [
                [
                    'requisition_item_id' => $item->id,
                    'action'              => 'transfer',
                    'quantity'            => 10.0,
                ],
            ],
        ], $this->adminUser->id);
    }

    #[Test]
    public function chlorine_item_with_partial_stock_suggests_partial_triage_with_quantities(): void
    {
        // 1. تجهيز صنف كلور (المطلوب 10، والمتوفر بالمخزن 4 فقط)
        [$chlorineProduct, $chlorineUnit] = $this->createProduct('أسطوانة غاز كلور جزئية', 'by_weight_serial', 180.0);
        $this->setStock($this->mainWarehouse->id, $chlorineProduct->id, 4.0);

        $requisition = $this->createApprovedRequisition($chlorineProduct, $chlorineUnit, 10.0);

        // 2. استعراض الفرز
        $overview = $this->requisitionService->getTriageOverview($requisition, $this->mainWarehouse->id);
        $itemOverview = $overview['items'][0];

        // 3. التحقق من التوجيه الجزئي وحساب الكميات بدقة
        $this->assertTrue($itemOverview['is_chlorine']);
        $this->assertFalse($itemOverview['can_fulfill_from_stock'], 'لا يغطي كامل الكمية.');
        $this->assertTrue($itemOverview['can_fulfill_partially'], 'يجب أن يؤكد إمكانية التغطية الجزئية.');
        $this->assertEquals('partial', $itemOverview['suggested_action'], 'يجب أن يقترح partial بدلاً من رفض المخزون بالكامل.');
        $this->assertEquals('transfer', $itemOverview['suggested_internal_action'], 'الإجراء الداخلي للكمية المتوفرة يجب أن يكون تحويل مخازن.');
        $this->assertEquals(4.0, (float) $itemOverview['available_stock']);
        $this->assertEquals(4.0, (float) $itemOverview['fulfillable_quantity'], 'الكمية القابلة للتغطية هي 4.');
        $this->assertEquals(6.0, (float) $itemOverview['shortage_quantity'], 'كمية العجز للشراء هي 6.');
        $this->assertEquals($this->chlorineOperatingWarehouse->id, $itemOverview['suggested_to_warehouse_id']);
    }

    #[Test]
    public function executing_partial_triage_for_chlorine_transfers_available_stock_and_keeps_requisition_approved_for_remaining(): void
    {
        // 1. طلب 10 أسطوانات مع توفر 4 فقط
        [$chlorineProduct, $chlorineUnit] = $this->createProduct('أسطوانة غاز كلور تنفيذ جزئي', 'by_weight_serial', 180.0);
        $this->setStock($this->mainWarehouse->id, $chlorineProduct->id, 4.0);

        $requisition = $this->createApprovedRequisition($chlorineProduct, $chlorineUnit, 10.0);
        /** @var PurchaseRequisitionItem $requisitionItem */
        $requisitionItem = $requisition->items->first();

        // 2. تنفيذ الفرز بتحويل الـ 4 المتوفرة فقط
        $triagePayload = [
            'warehouse_id' => $this->mainWarehouse->id,
            'items' => [
                [
                    'requisition_item_id' => $requisitionItem->id,
                    'action'              => 'transfer',
                    'quantity'            => 4.0,
                    'to_warehouse_id'     => $this->chlorineOperatingWarehouse->id,
                ],
            ],
        ];

        $result = $this->requisitionService->executeTriage($requisition, $triagePayload, $this->adminUser->id);

        // 3. التحقق من إنشاء أمر تحويل بالـ 4 فقط
        $this->assertInstanceOf(Transfer::class, $result['draft_transfer']);
        /** @var Transfer $transfer */
        $transfer = $result['draft_transfer'];
        $this->assertEquals(4.0, (float) $transfer->items->first()->quantity);

        // 4. التحقق من بقاء الطلب معتمداً وبقاء 6 أسطوانات متبقية للشراء
        $requisitionItem->refresh();
        $this->assertEquals(4.0, (float) $requisitionItem->quantity_transferred);
        $this->assertEquals(6.0, (float) $requisitionItem->remaining_quantity);

        $requisition->refresh();
        $this->assertEquals(RequisitionStatus::APPROVED, $requisition->status, 'يجب أن تظل حالة الطلب معتمد لوجود عجز متبقي.');
    }

    #[Test]
    public function triage_execution_fails_if_cumulative_quantities_exceed_remaining_quantity(): void
    {
        // 1. طلب 10 أسطوانات
        [$chlorineProduct, $chlorineUnit] = $this->createProduct('أسطوانة كلور فحص التراكمي', 'by_weight_serial', 180.0);
        $this->setStock($this->mainWarehouse->id, $chlorineProduct->id, 10.0);

        $requisition = $this->createApprovedRequisition($chlorineProduct, $chlorineUnit, 10.0);
        /** @var PurchaseRequisitionItem $requisitionItem */
        $requisitionItem = $requisition->items->first();

        // 2. محاولة إرسال بندين لنفس الصنف بمجموع 11 (6 تحويل + 5 شراء)
        $triagePayload = [
            'warehouse_id' => $this->mainWarehouse->id,
            'items' => [
                [
                    'requisition_item_id' => $requisitionItem->id,
                    'action'              => 'transfer',
                    'quantity'            => 6.0,
                    'to_warehouse_id'     => $this->chlorineOperatingWarehouse->id,
                ],
                [
                    'requisition_item_id' => $requisitionItem->id,
                    'action'              => 'purchase',
                    'quantity'            => 5.0,
                ],
            ],
        ];

        // 3. التحقق من إطلاق استثناء التحقق التراكمي
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('إجمالي الكميات الموزعة');

        $this->requisitionService->executeTriage($requisition, $triagePayload, $this->adminUser->id);
    }

    // =========================================================================
    // الدوال المساعدة لتجهيز البيانات (Isolated Fixture Helpers)
    // =========================================================================

    protected function createMeasurementUnit(string $name, string $code): Unit
    {
        $payload = ['name' => $name];

        if (Schema::hasColumn('inventory_units', 'code')) {
            $payload['code'] = $code;
        }
        if (Schema::hasColumn('inventory_units', 'symbol')) {
            $payload['symbol'] = $code;
        }
        if (Schema::hasColumn('inventory_units', 'is_active')) {
            $payload['is_active'] = true;
        }

        return Unit::create($payload);
    }

    protected function createProduct(string $name, string $trackingType, float $costPrice): array
    {
        $product = Product::create([
            'name'             => $name,
            'sku'              => 'SKU-' . uniqid(),
            'type'             => 'storable',
            'inventory_policy' => 'standard',
            'tracking_type'    => $trackingType,
            'valuation_method' => 'fifo',
            'cost_price'       => $costPrice,
            'is_active'        => true,
        ]);

        $productUnit = ProductUnit::create([
            'product_id'        => $product->id,
            'unit_id'           => $this->baseUnit->id,
            'conversion_factor' => 1.000000,
            'is_base_unit'      => true,
            'is_purchase_unit'  => true,
            'is_sale_unit'      => true,
        ]);

        return [$product, $productUnit];
    }

    protected function setStock(int $warehouseId, int $productId, float $quantity, float $reserved = 0.0): ProductStock
    {
        return ProductStock::create([
            'warehouse_id'      => $warehouseId,
            'product_id'        => $productId,
            'quantity'          => $quantity,
            'reserved_quantity' => $reserved,
        ]);
    }

    protected function createApprovedRequisition(
        Product $product,
        ProductUnit $unit,
        float $quantityRequested
    ): PurchaseRequisition {
        $requisition = PurchaseRequisition::create([
            'requisition_number' => 'PR-' . uniqid(),
            'requested_by'       => $this->adminUser->id,
            'request_date'       => Carbon::now()->toDateString(),
            'priority'           => RequisitionPriority::HIGH,
            'status'             => RequisitionStatus::APPROVED,
            'approved_by'        => $this->adminUser->id,
            'approved_at'        => Carbon::now(),
            'created_by'         => $this->adminUser->id,
            'updated_by'         => $this->adminUser->id,
        ]);

        $requisition->items()->create([
            'product_id'           => $product->id,
            'item_name'            => $product->name,
            'product_unit_id'      => $unit->id,
            'unit_name'            => 'كيلوجرام',
            'quantity_requested'   => $quantityRequested,
            'quantity_approved'    => $quantityRequested,
            'quantity_ordered'     => 0.0000,
            'quantity_issued'      => 0.0000,
            'quantity_transferred' => 0.0000,
            'estimated_unit_cost'  => (float) $product->cost_price,
            'created_by'           => $this->adminUser->id,
            'updated_by'           => $this->adminUser->id,
        ]);

        return $requisition->load(['items.product', 'items.productUnit']);
    }
}