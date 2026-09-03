<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Policies\InventoryReportPolicy;
use App\Modules\Inventory\Services\Reports\BatchExpiryReportService;
use App\Modules\Inventory\Services\Reports\InventoryIntegrityAuditService;
use App\Modules\Inventory\Services\Reports\ItemStockCardReportService;
use App\Modules\Inventory\Services\Reports\ProductionVarianceReportService;
use App\Modules\Inventory\Services\Reports\ReorderAlertReportService;
use App\Modules\Inventory\Services\Reports\SerialTrackingReportService;
use App\Modules\Inventory\Services\Reports\StockBalanceReportService;
use App\Modules\Inventory\Services\Reports\StockDiscrepancyReportService;
use App\Modules\Inventory\Services\Reports\TransferTrackingReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryReportController extends Controller
{
    public function __construct(
        protected ItemStockCardReportService $itemStockCardService,
        protected StockBalanceReportService $stockBalanceService,
        protected InventoryIntegrityAuditService $integrityAuditService,
        protected StockDiscrepancyReportService $stockDiscrepancyService,
        protected TransferTrackingReportService $transferTrackingService,
        protected BatchExpiryReportService $batchExpiryService,
        protected SerialTrackingReportService $serialTrackingService,
        protected ReorderAlertReportService $reorderAlertService,
        protected ProductionVarianceReportService $productionVarianceService
    ) {}

    /**
     * 1. تقرير كارت الصنف التفصيلي والرصيد التراكمي
     */
    public function itemStockCard(Request $request): JsonResponse
    {
        $this->authorize('viewStockCard', InventoryReportPolicy::class);

        $request->validate([
            'product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:inventory_warehouses,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $data = $this->itemStockCardService->generate($request->all());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 2. تقرير أرصدة وتقييم المخزون اللحظي
     */
    public function stockBalance(Request $request): JsonResponse
    {
        $this->authorize('viewStockBalance', InventoryReportPolicy::class);

        $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:inventory_warehouses,id'],
            'category_id' => ['nullable', 'integer', 'exists:inventory_categories,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'only_in_stock' => ['nullable', 'boolean'],
        ]);

        $data = $this->stockBalanceService->generate($request->all());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 3. تقرير تدقيق المطابقة وفحص سلامة البيانات المخزنية
     */
    public function integrityAudit(Request $request): JsonResponse
    {
        $this->authorize('viewIntegrityAudit', InventoryReportPolicy::class);

        $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:inventory_warehouses,id'],
        ]);

        $data = $this->integrityAuditService->generate($request->all());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 4. تقرير تسويات وفروقات الجرد الفعلي والدفتري
     */
    public function stockDiscrepancy(Request $request): JsonResponse
    {
        $this->authorize('viewDiscrepancies', InventoryReportPolicy::class);

        $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:inventory_warehouses,id'],
            'status' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $data = $this->stockDiscrepancyService->generate($request->all());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 5. تقرير متابعة التحويلات بين المستودعات
     */
    public function transferTracking(Request $request): JsonResponse
    {
        $this->authorize('viewTransfersTracking', InventoryReportPolicy::class);

        $request->validate([
            'from_warehouse_id' => ['nullable', 'integer', 'exists:inventory_warehouses,id'],
            'to_warehouse_id' => ['nullable', 'integer', 'exists:inventory_warehouses,id'],
            'status' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $data = $this->transferTrackingService->generate($request->all());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 6. تقرير صلاحيات ودفعات التشغيل والراكد
     */
    public function batchExpiry(Request $request): JsonResponse
    {
        $this->authorize('viewBatchExpiry', InventoryReportPolicy::class);

        $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:inventory_products,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:inventory_warehouses,id'],
            'days_threshold' => ['nullable', 'integer', 'min:1'],
        ]);

        $data = $this->batchExpiryService->generate($request->all());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 7. تقرير تتبع ومسار الأرقام التسلسلية
     */
    public function serialTracking(Request $request): JsonResponse
    {
        $this->authorize('viewSerialTracking', InventoryReportPolicy::class);

        $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:inventory_products,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:inventory_warehouses,id'],
            'status' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $data = $this->serialTrackingService->generate($request->all());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 8. تقرير نواقص المخزون وحدود إعادة الطلب
     */
    public function reorderAlerts(Request $request): JsonResponse
    {
        $this->authorize('viewReorderAlerts', InventoryReportPolicy::class);

        $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:inventory_warehouses,id'],
        ]);

        $data = $this->reorderAlertService->generate($request->all());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 9. تقرير انحرافات واستهلاك وتكاليف الإنتاج
     */
    public function productionVariance(Request $request): JsonResponse
    {
        $this->authorize('viewProductionVariance', InventoryReportPolicy::class);

        $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:inventory_products,id'],
            'status' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $data = $this->productionVarianceService->generate($request->all());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}