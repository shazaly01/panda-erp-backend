<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use Tests\ApiTestCase;
use App\Modules\Inventory\Enums\StockSerialStatus;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductUnit;
use App\Modules\Inventory\Models\StockSerial;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Database\Seeders\InventoryPermissionsSeeder;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class StockSerialLifecycleTest extends ApiTestCase
{
    protected Warehouse $mainWarehouse;
    protected Warehouse $operatingWarehouse;
    protected Product $chlorineProduct;
    protected Unit $baseUnit;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. تشغيل Seeder الصلاحيات لمنح المستخدم الصلاحيات اللازمة
        $this->seed(InventoryPermissionsSeeder::class);
        $this->adminUser->givePermissionTo([
            'inventory.serials.view',
            'inventory.serials.create',
            'inventory.serials.update',
            'inventory.serials.delete',
        ]);

        // 2. إنشاء المخازن (المخزن الرئيسي ومخزن التشغيل بالمحطة)
        $this->mainWarehouse = Warehouse::create([
            'code'         => 'WH-MAIN-TEST',
            'name'         => 'المستودع الرئيسي للمحطة',
            'is_operating' => false,
            'is_active'    => true,
        ]);

        $this->operatingWarehouse = Warehouse::create([
            'code'         => 'WH-OP-TEST',
            'name'         => 'مخزن عنبر حقن الكلور التشغيلي',
            'is_operating' => true,
            'is_active'    => true,
        ]);

        // 3. تجهيز الوحدة وصنف الكلور
        $this->baseUnit = $this->createMeasurementUnit('كيلوجرام', 'KG');

        $this->chlorineProduct = Product::create([
            'name'             => 'غاز كلور مسال',
            'sku'              => 'CHL-TEST-' . uniqid(),
            'type'             => 'storable',
            'inventory_policy' => 'standard',
            'tracking_type'    => 'by_weight_serial',
            'valuation_method' => 'fifo',
            'cost_price'       => 120.0,
            'is_active'        => true,
        ]);

        ProductUnit::create([
            'product_id'        => $this->chlorineProduct->id,
            'unit_id'           => $this->baseUnit->id,
            'conversion_factor' => 1.0,
            'is_base_unit'      => true,
            'is_purchase_unit'  => true,
            'is_sale_unit'      => true,
        ]);
    }

    #[Test]
    public function user_can_return_empty_serials_from_operating_to_main_warehouse(): void
    {
        // تجهيز أسطوانتين فارغتين بمخزن التشغيل
        $serial1 = $this->createSerial('CYL-001', StockSerialStatus::EMPTY, $this->operatingWarehouse->id, 45.0, 45.0, 0.0);
        $serial2 = $this->createSerial('CYL-002', StockSerialStatus::EMPTY, $this->operatingWarehouse->id, 46.0, 46.0, 0.0);

        $payload = [
            'warehouse_id' => $this->mainWarehouse->id,
            'serial_ids'   => [$serial1->id, $serial2->id],
            'notes'        => 'إرجاع أسطوانات فارغة من عنبر الحقن للمخزن الرئيسي',
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson(route('inventory.serials.return-to-main'), $payload);

        $response->assertOk()
            ->assertJsonPath('count', 2);

        $serial1->refresh();
        $serial2->refresh();

        $this->assertEquals($this->mainWarehouse->id, $serial1->warehouse_id);
        $this->assertEquals(StockSerialStatus::EMPTY, $serial1->status);
        $this->assertEquals($this->mainWarehouse->id, $serial2->warehouse_id);
        $this->assertEquals(StockSerialStatus::EMPTY, $serial2->status);
    }

    #[Test]
    public function return_to_main_fails_if_serial_is_not_empty(): void
    {
        // أسطوانة ما تزال قيد التشغيل في المحطة
        $serial = $this->createSerial('CYL-003', StockSerialStatus::IN_SERVICE, $this->operatingWarehouse->id, 45.0, 95.0, 50.0);

        $payload = [
            'warehouse_id' => $this->mainWarehouse->id,
            'serial_ids'   => [$serial->id],
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson(route('inventory.serials.return-to-main'), $payload);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => "الأسطوانة ({$serial->serial_number}) ليست بحالة فارغة (حالتها الحالية: {$serial->status->label()})، لا يمكن إرجاعها للمخزن الرئيسي كفارغة.",
            ]);
    }

    #[Test]
    public function user_can_dispatch_empty_serials_from_main_warehouse_to_vendor(): void
    {
        // تجهيز أسطوانة فارغة متواجدة في المخزن الرئيسي
        $serial = $this->createSerial('CYL-004', StockSerialStatus::EMPTY, $this->mainWarehouse->id, 45.0, 45.0, 0.0);

        $payload = [
            'serial_ids'  => [$serial->id],
            'vendor_name' => 'مصنع النيل لتعبئة الغازات الصناعية',
            'notes'       => 'شحنة تعبئة رقم 104',
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson(route('inventory.serials.dispatch-to-vendor'), $payload);

        $response->assertOk()
            ->assertJsonPath('count', 1);

        $serial->refresh();
        $this->assertEquals(StockSerialStatus::WITH_VENDOR, $serial->status);
        $this->assertNull($serial->warehouse_id, 'عند خروج الأسطوانة للمورد يفرغ حقل المستودع');
        $this->assertStringContainsString('مصنع النيل لتعبئة الغازات الصناعية', (string) $serial->notes);
    }

    #[Test]
    public function dispatch_to_vendor_fails_if_serial_is_still_in_operating_warehouse(): void
    {
        // أسطوانة فارغة ولكنها لا تزال في مخزن التشغيل ولم تعد للمخزن الرئيسي
        $serial = $this->createSerial('CYL-005', StockSerialStatus::EMPTY, $this->operatingWarehouse->id, 45.0, 45.0, 0.0);

        $payload = [
            'serial_ids' => [$serial->id],
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson(route('inventory.serials.dispatch-to-vendor'), $payload);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => "الأسطوانة ({$serial->serial_number}) لا تزال في مخزن التشغيل بالمحطة؛ يجب إرجاعها إلى المخزن الرئيسي أولاً قبل تسليمها للمورد.",
            ]);
    }

    #[Test]
    public function user_can_update_serial_basic_data(): void
    {
        $serial = $this->createSerial('CYL-ORIGINAL', StockSerialStatus::EMPTY, $this->mainWarehouse->id, 44.0, 44.0, 0.0);

        $payload = [
            'serial_number' => 'CYL-CORRECTED',
            'tare_weight'   => 45.5,
            'notes'         => 'تصحيح الوزن الفارغ بعد المعايرة الدورية',
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson(route('inventory.serials.update', $serial), $payload);

        $response->assertOk()
            ->assertJsonPath('data.serial_number', 'CYL-CORRECTED')
            ->assertJsonPath('data.tare_weight', 45.5);

        $serial->refresh();
        $this->assertEquals('CYL-CORRECTED', $serial->serial_number);
        $this->assertEquals(45.5, (float) $serial->tare_weight);
        $this->assertEquals(45.5, (float) $serial->gross_weight, 'للأسطوانة الفارغة يتطابق الوزن القائم مع الوزن الفارغ');
    }

    #[Test]
    public function user_can_revert_accidental_dispatch_to_vendor_back_to_empty(): void
    {
        // أسطوانة تم شحنها للمورد بالخطأ
        $serial = $this->createSerial('CYL-ERR-01', StockSerialStatus::WITH_VENDOR, null, 45.0, 45.0, 0.0);

        $payload = [
            'revert_to_empty' => true,
            'warehouse_id'    => $this->mainWarehouse->id,
            'notes'           => 'تراجع عن التسليم: الأسطوانة لم تغادر بوابة المحطة',
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson(route('inventory.serials.update', $serial), $payload);

        $response->assertOk()
            ->assertJsonPath('data.status', StockSerialStatus::EMPTY->value)
            ->assertJsonPath('data.warehouse_id', $this->mainWarehouse->id);

        $serial->refresh();
        $this->assertEquals(StockSerialStatus::EMPTY, $serial->status);
        $this->assertEquals($this->mainWarehouse->id, $serial->warehouse_id);
    }

    #[Test]
    public function revert_to_empty_fails_if_serial_is_not_with_vendor(): void
    {
        $serial = $this->createSerial('CYL-ALREADY-STOCK', StockSerialStatus::IN_STOCK, $this->mainWarehouse->id, 45.0, 95.0, 50.0);

        $payload = [
            'revert_to_empty' => true,
            'warehouse_id'    => $this->mainWarehouse->id,
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson(route('inventory.serials.update', $serial), $payload);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => "لا يمكن التراجع عن حالة الأسطوانة إلا إذا كانت حالتها حالياً لدى المورد (الحالة الحالية: {$serial->status->label()}).",
            ]);
    }

    // =========================================================================
    // Isolated Fixture Helpers
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

    protected function createSerial(
        string $serialNumber,
        StockSerialStatus $status,
        ?int $warehouseId,
        float $tareWeight,
        float $grossWeight,
        float $gasWeight
    ): StockSerial {
        return StockSerial::create([
            'product_id'         => $this->chlorineProduct->id,
            'warehouse_id'       => $warehouseId,
            'serial_number'      => $serialNumber,
            'tare_weight'        => $tareWeight,
            'gross_weight'       => $grossWeight,
            'current_gas_weight' => $gasWeight,
            'status'             => $status,
        ]);
    }
}