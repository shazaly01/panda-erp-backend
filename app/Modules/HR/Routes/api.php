<?php

use Illuminate\Support\Facades\Route;

// --- استيراد المتحكمات (Controllers) ---
use App\Modules\HR\Http\Controllers\DepartmentController;
use App\Modules\HR\Http\Controllers\PositionController;
use App\Modules\HR\Http\Controllers\EmployeeController;
use App\Modules\HR\Http\Controllers\ContractController;
use App\Modules\HR\Http\Controllers\SalaryRuleController;
use App\Modules\HR\Http\Controllers\SalaryStructureController;
use App\Modules\HR\Http\Controllers\PayrollController;
use App\Modules\HR\Http\Controllers\LeaveRequestController;
use App\Modules\HR\Http\Controllers\LoanController;
use App\Modules\HR\Http\Controllers\AttendanceLogController;
use App\Modules\HR\Http\Controllers\PayrollInputController;
use App\Modules\HR\Http\Controllers\ShiftController;

/*
|--------------------------------------------------------------------------
| HR Module API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')
    ->prefix('hr') // 🌟 القفل والمفتاح: هذه البادئة تجعل الروابط تطابق طلبات الـ Vue Service
    ->group(function () {

    // ===========================================
    // 1. الهيكل التنظيمي (Organizational Structure)
    // ===========================================
    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('positions', PositionController::class);

    // ===========================================
    // 2. شؤون الموظفين (Employee Management)
    // ===========================================
    Route::apiResource('employees', EmployeeController::class);

    // ملاحظة: تم تفعيل الـ update للعقود كما طلبتم سابقاً
    Route::post('contracts/{contract}/terminate', [ContractController::class, 'terminate']);
    Route::apiResource('contracts', ContractController::class);

    // ===========================================
    // 3. إعدادات الرواتب (Payroll Settings)
    // ===========================================
   Route::apiResource('salary-rules', SalaryRuleController::class)
    ->parameters(['salary-rules' => 'salary_rule']);


   Route::apiResource('salary-structures', SalaryStructureController::class)
        ->parameters(['salary-structures' => 'salary_structure']);

    // ===========================================
    // 4. العمليات والخدمة الذاتية (Operations & Self-Service)
    // ===========================================

    // الإجازات
    Route::post('leave-requests/{leave_request}/approve', [LeaveRequestController::class, 'approve']);
    Route::apiResource('leave-requests', LeaveRequestController::class);

    // السلف والعهد
    Route::post('loans/{loan}/approve', [LoanController::class, 'approve']);
    Route::post('loans/{loan}/mark-as-paid', [LoanController::class, 'markAsPaid']);
    Route::apiResource('loans', LoanController::class);


    Route::get('employees/{employee}/shifts', [ShiftController::class, 'employeeShifts']); // عرض ورديات موظف محدد
    Route::post('shifts/assign', [ShiftController::class, 'assignEmployee']); // تعيين موظف على وردية
    Route::apiResource('shifts', ShiftController::class); // إدارة الورديات الأساسية (CRUD)

    // الحضور والانصراف
    Route::apiResource('attendance-logs', AttendanceLogController::class);

    // المدخلات المالية المتغيرة (حوافز/خصومات)
    Route::apiResource('payroll-inputs', PayrollInputController::class);

    // ===========================================
    // 5. معالجة الرواتب (Payroll Processing)
    // ===========================================
    Route::prefix('payroll')->name('payroll.')->group(function () {
        // معاينة الراتب (Preview)
        Route::post('preview', [PayrollController::class, 'preview'])
            ->middleware('can:hr.payroll.view');

        // اعتماد وترحيل الرواتب (Post Batch)
        Route::post('post-batch', [PayrollController::class, 'postBatch'])
            ->middleware('can:hr.payroll.post');
    });

});
