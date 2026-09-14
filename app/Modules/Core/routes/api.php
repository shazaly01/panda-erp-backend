<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Modules\Core\Http\Controllers\SequenceController;
use App\Modules\Core\Http\Controllers\PartnerController;

/*
|--------------------------------------------------------------------------
| Core API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')
    ->prefix('core')
    ->group(function () {

        // ===========================================
        // إعدادات ترقيم المستندات (Sequences Settings)
        // ===========================================

        // جلب جميع إعدادات الترقيم
        Route::get('sequences', [SequenceController::class, 'index']);

        // عرض إعداد ترقيم محدد
        Route::get('sequences/{sequence}', [SequenceController::class, 'show']);

        // تحديث إعداد الترقيم (الصيغة وطريقة التصفير)
        Route::put('sequences/{sequence}', [SequenceController::class, 'update']);

        // ===========================================
        // إدارة جهات التعامل: العملاء والموردين (Partners)
        // ===========================================

        // نقطة نهاية جلب القوائم السريعة للشركاء (يجب وضعها قبل apiResource لتفادي تضارب المعرفات)
        Route::get('partners/selector', [PartnerController::class, 'selector']);

        // العمليات المعيارية للشركاء
        Route::apiResource('partners', PartnerController::class);

    });