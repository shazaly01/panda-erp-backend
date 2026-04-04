<?php

use Illuminate\Support\Facades\Route;
use App\Modules\HR\Http\Controllers\DepartmentController;
use App\Modules\HR\Http\Controllers\PositionController;
use App\Modules\HR\Http\Controllers\EmployeeController;
use App\Modules\HR\Http\Controllers\ContractController;
// --- استيراد المتحكمات الجديدة المطلوبة للرواتب ---
use App\Modules\HR\Http\Controllers\SalaryRuleController;
use App\Modules\HR\Http\Controllers\SalaryStructureController;
use App\Modules\HR\Http\Controllers\PayrollController;

/*
|--------------------------------------------------------------------------
| HR Module API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // 1. الإدارات والهيكل التنظيمي (موجود لديك)
    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('positions', PositionController::class);

    // 2. الموظفين (موجود لديك)
    Route::apiResource('employees', EmployeeController::class);

    // 3. العقود (موجود لديك)
    Route::apiResource('contracts', ContractController::class)->except(['update']);

    // --- 4. إعدادات الرواتب (إضافة جديدة وضرورية) ---
    // بدونهما لا يمكنك تعريف البدلات والخصومات
    Route::apiResource('salary-rules', SalaryRuleController::class);
    Route::apiResource('salary-structures', SalaryStructureController::class);

    // --- 5. عمليات الرواتب (تحديث) ---
    Route::prefix('payroll')->name('payroll.')->group(function () {

        // أ. معاينة الراتب (موجود لديك)
        Route::post('preview', [PayrollController::class, 'preview'])
            ->middleware('can:hr.payroll.view'); // إضافة حماية الصلاحية

        // ب. اعتماد وترحيل الرواتب (الرابط الجديد للربط المحاسبي)
        // هذا هو الزر الذي يفعّل PayrollPostingService
        Route::post('post-batch', [PayrollController::class, 'postBatch'])
            ->middleware('can:hr.payroll.post');
    });

});
