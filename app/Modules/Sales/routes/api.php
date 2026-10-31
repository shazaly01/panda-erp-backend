<?php

declare(strict_types=1);

use App\Modules\Sales\Http\Controllers\PosShiftController;
use App\Modules\Sales\Http\Controllers\SalesInvoiceController;
use App\Modules\Sales\Http\Controllers\SalesInvoicePaymentController;
use App\Modules\Sales\Http\Controllers\SalesReturnController;
use App\Modules\Sales\Http\Controllers\SalesReturnPaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('sales')
    ->group(function () {

        // ===========================================
        // 1. ورديات وجلسات نقاط البيع والكاشير (POS Shifts)
        // ===========================================

        // الاستعلام عن الوردية المفتوحة حالياً للمستخدم المسجل
        Route::get('shifts/current', [PosShiftController::class, 'current']);

        // فحص ومطابقة مبالغ وفروقات الوردية
        Route::get('shifts/{posShift}/reconcile', [PosShiftController::class, 'reconcile']);

        // إغلاق جلسة الوردية وتسوية النقد الفعلي
        Route::post('shifts/{posShift}/close', [PosShiftController::class, 'close']);

        // إدارة ورديات نقاط البيع
        Route::apiResource('shifts', PosShiftController::class)
            ->parameters(['shifts' => 'posShift']);

        // ===========================================
        // 2. فواتير المبيعات (Sales Invoices)
        // ===========================================

        // تأكيد واعتماد فاتورة المبيعات
        Route::post('invoices/{salesInvoice}/confirm', [SalesInvoiceController::class, 'confirm']);

        // الترحيل المحاسبي وتوليد حركة الخصم المخزني والقيود المالية
        Route::post('invoices/{salesInvoice}/post', [SalesInvoiceController::class, 'post']);

        // إلغاء الفاتورة وعكس الأثر المالي والمخزني
        Route::post('invoices/{salesInvoice}/cancel', [SalesInvoiceController::class, 'cancel']);

        // إدارة فواتير المبيعات
        Route::apiResource('invoices', SalesInvoiceController::class)
            ->parameters(['invoices' => 'salesInvoice']);

        // ===========================================
        // 3. مدفوعات وتحصيلات الفواتير (Sales Invoice Payments)
        // ===========================================

        // إدارة سندات تحصيل وقبض مبيعات الفواتير
        Route::apiResource('payments', SalesInvoicePaymentController::class)
            ->parameters(['payments' => 'payment']);

        // ===========================================
        // 4. مرتجعات المبيعات والإشعارات الدائنة (Sales Returns)
        // ===========================================

        // تأكيد واعتماد مرتجع المبيعات
        Route::post('returns/{salesReturn}/confirm', [SalesReturnController::class, 'confirm']);

        // ترحيل المرتجع وعكس القيود المالية وإعادة البضاعة السليمة للمخزن
        Route::post('returns/{salesReturn}/post', [SalesReturnController::class, 'post']);

        // إلغاء المرتجع وعكس الحركات المخزنية والقيود
        Route::post('returns/{salesReturn}/cancel', [SalesReturnController::class, 'cancel']);

        // إدارة مرتجعات المبيعات
        Route::apiResource('returns', SalesReturnController::class)
            ->parameters(['returns' => 'salesReturn']);

        // ===========================================
        // 5. سندات استرداد مبالغ المرتجعات (Sales Return Payments)
        // ===========================================

        // إدارة سندات صرف ورد الأموال للمرتجعات
        Route::apiResource('return-payments', SalesReturnPaymentController::class)
            ->parameters(['return-payments' => 'payment']);

    });