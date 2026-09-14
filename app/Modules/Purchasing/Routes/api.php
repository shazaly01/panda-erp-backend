<?php

declare(strict_types=1);

use App\Modules\Purchasing\Http\Controllers\PurchaseBillController;
use App\Modules\Purchasing\Http\Controllers\PurchaseIssueController;
use App\Modules\Purchasing\Http\Controllers\PurchaseOrderController;
use App\Modules\Purchasing\Http\Controllers\PurchaseReceiptController;
use App\Modules\Purchasing\Http\Controllers\PurchaseRequisitionController;
use App\Modules\Purchasing\Http\Controllers\PurchaseReturnController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('purchasing')
    ->group(function () {

        // ===========================================
        // 1. طلبات الشراء والاحتياج الداخلي (Purchase Requisitions)
        // ===========================================

        // استعراض الفرز الذكي وقراءة الأرصدة اللحظية للمستودع
        Route::get('requisitions/{requisition}/triage-overview', [PurchaseRequisitionController::class, 'triageOverview']);

        // تنفيذ التوزيع الآلي وتوليد مسودة إذن الصرف المخزني
        Route::post('requisitions/{requisition}/triage', [PurchaseRequisitionController::class, 'executeTriage']);

        // تقديم الطلب للاعتماد
        Route::post('requisitions/{requisition}/submit', [PurchaseRequisitionController::class, 'submit']);

        // اعتماد طلب الشراء
        Route::post('requisitions/{requisition}/approve', [PurchaseRequisitionController::class, 'approve']);

        // رفض طلب الشراء
        Route::post('requisitions/{requisition}/reject', [PurchaseRequisitionController::class, 'reject']);

        // إدارة طلبات الشراء
        Route::apiResource('requisitions', PurchaseRequisitionController::class);

        // ===========================================
        // 2. أذونات صرف المواد المخزنية (Purchase Issues)
        // ===========================================

        // تأكيد الصرف والترحيل والخصم اللحظي من المخزن
        Route::post('issues/{issue}/confirm', [PurchaseIssueController::class, 'confirm']);

        // إلغاء إذن الصرف وعكس الأثر المخزني
        Route::post('issues/{issue}/cancel', [PurchaseIssueController::class, 'cancel']);

        // إدارة أذونات الصرف المخزني
        Route::apiResource('issues', PurchaseIssueController::class);

        // ===========================================
        // 3. أوامر الشراء (Purchase Orders)
        // ===========================================

        // تأكيد واعتماد أمر الشراء
        Route::post('orders/{order}/confirm', [PurchaseOrderController::class, 'confirm']);

        // إلغاء أمر الشراء
        Route::post('orders/{order}/cancel', [PurchaseOrderController::class, 'cancel']);

        // إدارة أوامر الشراء
        Route::apiResource('orders', PurchaseOrderController::class);

        // ===========================================
        // 4. سندات الاستلام المخزني (Purchase Receipts)
        // ===========================================

        // تأكيد الاستلام وترحيل الأثر المخزني
        Route::post('receipts/{receipt}/receive', [PurchaseReceiptController::class, 'receive']);

        // إلغاء سند الاستلام وعكس الحركات المخزنية
        Route::post('receipts/{receipt}/cancel', [PurchaseReceiptController::class, 'cancel']);

        // إدارة سندات الاستلام المخزني
        Route::apiResource('receipts', PurchaseReceiptController::class);

        // ===========================================
        // 5. فواتير المشتريات (Purchase Bills)
        // ===========================================

        // الترحيل المحاسبي وتوليد القيد المالي
        Route::post('bills/{bill}/post', [PurchaseBillController::class, 'post']);

        // إلغاء الفاتورة وعكس القيود والتسويات المالية
        Route::post('bills/{bill}/cancel', [PurchaseBillController::class, 'cancel']);

        // إدارة فواتير المشتريات
        Route::apiResource('bills', PurchaseBillController::class);

        // ===========================================
        // 6. مردودات المشتريات (Purchase Returns)
        // ===========================================

        // ترحيل المردود وتطبيق الأثر المخزني والمالي
        Route::post('returns/{return}/post', [PurchaseReturnController::class, 'post']);

        // إلغاء المردود وعكس الحركات المخزنية والقيود المالية
        Route::post('returns/{return}/cancel', [PurchaseReturnController::class, 'cancel']);

        // إدارة مردودات المشتريات
        Route::apiResource('returns', PurchaseReturnController::class);

    });