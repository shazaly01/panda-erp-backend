<?php

declare(strict_types=1);

use App\Modules\Inventory\Http\Controllers\AdjustmentController;
use App\Modules\Inventory\Http\Controllers\CategoryController;
use App\Modules\Inventory\Http\Controllers\GasLeakageController;
use App\Modules\Inventory\Http\Controllers\InventoryReportController;
use App\Modules\Inventory\Http\Controllers\PriceListController;
use App\Modules\Inventory\Http\Controllers\ProductController;
use App\Modules\Inventory\Http\Controllers\StockSerialController;
use App\Modules\Inventory\Http\Controllers\TransferController;
use App\Modules\Inventory\Http\Controllers\UnitController;
use App\Modules\Inventory\Http\Controllers\WarehouseController;
use App\Modules\Inventory\Http\Controllers\WarehouseLocationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('inventory')
    ->group(function () {

        // ===========================================
        // 1. البيانات الأساسية والهياكل (Master Data)
        // ===========================================

        // وحدات القياس (Units)
        Route::apiResource('units', UnitController::class);

        // التصنيفات (Categories)
        Route::apiResource('categories', CategoryController::class);

        // المستودعات (Warehouses)
        Route::apiResource('warehouses', WarehouseController::class);

        // مواقع ورفوف المستودعات (Warehouse Locations)
        Route::apiResource('warehouse-locations', WarehouseLocationController::class);

        // ===========================================
        // 2. الأصناف وقوائم الأسعار (Products & Pricing)
        // ===========================================

        // بحث فائق السرعة لنقاط البيع، الكاشير، والباركود والميزان والسيريال
        Route::get('products/fast-search', [ProductController::class, 'fastSearch']);

        // الأصناف (Products)
        Route::apiResource('products', ProductController::class);

        // قوائم الأسعار (Price Lists)
        Route::apiResource('price-lists', PriceListController::class);

        // ===========================================
        // 3. إدارة الأرقام التسلسلية والأوزان (Serials & Catch Weight)
        // ===========================================

        // جلب قائمة الأرقام التسلسلية (مع دعم الفلترة بحسب الحالة مثل with_vendor)
        Route::get('serials', [StockSerialController::class, 'index'])->name('inventory.serials.index');

        // عرض تفاصيل رقم تسلسلي / أسطوانة محددة
        Route::get('serials/{serial}', [StockSerialController::class, 'show'])->name('inventory.serials.show');

        // إثبات فراغ الأسطوانة بالمحطة وتحويل حالتها إلى فارغة بالمستودع
        Route::post('serials/{serial}/mark-as-empty', [StockSerialController::class, 'markAsEmpty'])->name('inventory.serials.mark-as-empty');

        // توثيق تسريب أو فاقد وزني لأسطوانة غاز وترحيل الأثر المخزني
        Route::post('gas-leakage', GasLeakageController::class)->name('inventory.gas-leakage.record');

        // ===========================================
        // 4. العمليات والتسويات المخزنية (Operations & Transfers & Adjustments)
        // ===========================================

        // اعتماد طلب التسوية / الرصيد الافتتاحي وتطبيق الأثر المخزني
        Route::post('adjustments/{adjustment}/approve', [AdjustmentController::class, 'approve']);

        // إدارة طلبات التسوية والأرصدة الافتتاحية (Adjustments)
        Route::apiResource('adjustments', AdjustmentController::class);

        // اعتماد وإكمال أمر التحويل المخزني وتطبيق الأثر
        Route::post('transfers/{transfer}/complete', [TransferController::class, 'complete']);

        // إلغاء أمر التحويل
        Route::post('transfers/{transfer}/cancel', [TransferController::class, 'cancel']);

        // إدارة أوامر التحويل بين المستودعات (Transfers)
        Route::apiResource('transfers', TransferController::class);

        // ===========================================
        // 5. التقارير والرقابة على المخزون (Reports & Auditing)
        // ===========================================
        Route::prefix('reports')->group(function () {
            // كارت الصنف التفصيلي والرصيد التراكمي
            Route::get('stock-card', [InventoryReportController::class, 'itemStockCard']);

            // أرصدة وتقييم المخزون اللحظي
            Route::get('stock-balance', [InventoryReportController::class, 'stockBalance']);

            // تقرير تدقيق المطابقة وفحص سلامة البيانات المخزنية
            Route::get('integrity-audit', [InventoryReportController::class, 'integrityAudit']);

            // تقرير تسويات وفروقات الجرد الدفتري والفعلي
            Route::get('discrepancies', [InventoryReportController::class, 'stockDiscrepancy']);

            // تقرير متابعة التحويلات بين المستودعات
            Route::get('transfers-tracking', [InventoryReportController::class, 'transferTracking']);

            // تقرير صلاحيات ودفعات التشغيل والراكد
            Route::get('batch-expiry', [InventoryReportController::class, 'batchExpiry']);

            // تقرير تتبع ومسار الأرقام التسلسلية
            Route::get('serial-tracking', [InventoryReportController::class, 'serialTracking']);

            // تقرير نواقص المخزون وحدود إعادة الطلب
            Route::get('reorder-alerts', [InventoryReportController::class, 'reorderAlerts']);

            // تقرير انحرافات واستهلاك وتكاليف الإنتاج
            Route::get('production-variance', [InventoryReportController::class, 'productionVariance']);
        });

    });