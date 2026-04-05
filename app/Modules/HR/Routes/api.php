<?php

use Illuminate\Support\Facades\Route;
use App\Modules\HR\Http\Controllers\DepartmentController;
use App\Modules\HR\Http\Controllers\PositionController;
use App\Modules\HR\Http\Controllers\EmployeeController;
use App\Modules\HR\Http\Controllers\ContractController;
use App\Modules\HR\Http\Controllers\SalaryRuleController;
use App\Modules\HR\Http\Controllers\SalaryStructureController;
use App\Modules\HR\Http\Controllers\PayrollController;

// --- استيراد المتحكمات التشغيلية والخدمة الذاتية (الجديدة) ---
use App\Modules\HR\Http\Controllers\LeaveRequestController;
use App\Modules\HR\Http\Controllers\LoanController;
use App\Modules\HR\Http\Controllers\AttendanceLogController;
use App\Modules\HR\Http\Controllers\PayrollInputController;

/*
|--------------------------------------------------------------------------
| HR Module API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // 1. الإدارات والهيكل التنظيمي
    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('positions', PositionController::class);

    // 2. الموظفين
    Route::apiResource('employees', EmployeeController::class);

    // 3. العقود
    Route::apiResource('contracts', ContractController::class)->except(['update']);

    // 4. إعدادات الرواتب
    Route::apiResource('salary-rules', SalaryRuleController::class);
    Route::apiResource('salary-structures', SalaryStructureController::class);

    // --- 5. الخدمة الذاتية والعمليات (الإضافات الجديدة) ---

    // أ. الإجازات
    Route::post('leave-requests/{leave_request}/approve', [LeaveRequestController::class, 'approve']);
    Route::apiResource('leave-requests', LeaveRequestController::class);

    // ب. السلف
    Route::post('loans/{loan}/approve', [LoanController::class, 'approve']);
    Route::post('loans/{loan}/mark-as-paid', [LoanController::class, 'markAsPaid']);
    Route::apiResource('loans', LoanController::class);

    // ج. الحضور والانصراف
    Route::apiResource('attendance-logs', AttendanceLogController::class);

    // د. المدخلات المالية المتغيرة (حوافز/خصومات)
    Route::apiResource('payroll-inputs', PayrollInputController::class);


    // --- 6. عمليات الرواتب ---
    Route::prefix('payroll')->name('payroll.')->group(function () {
        // معاينة الراتب (يتم تمرير employee_id و month)
        Route::post('preview', [PayrollController::class, 'preview'])
            ->middleware('can:hr.payroll.view');

        // اعتماد وترحيل الرواتب
        Route::post('post-batch', [PayrollController::class, 'postBatch'])
            ->middleware('can:hr.payroll.post');
    });

});
