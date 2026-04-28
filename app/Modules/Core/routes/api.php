<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Core\Http\Controllers\SequenceController;

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

    });
