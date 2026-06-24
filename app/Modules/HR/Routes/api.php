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
use App\Modules\HR\Http\Controllers\ManagerAttendanceController;
use App\Modules\HR\Http\Controllers\PayrollInputController;
use App\Modules\HR\Http\Controllers\ShiftController;
use App\Modules\HR\Http\Controllers\OvertimePolicyController;
use App\Modules\HR\Http\Controllers\PayGroupController;
use App\Modules\HR\Http\Controllers\PayPeriodController;

// 🌟 المتحكمات الجديدة الخاصة بمعمارية الجدولة
use App\Modules\HR\Http\Controllers\WorkingScheduleController;
use App\Modules\HR\Http\Controllers\CalendarExceptionController;
use App\Modules\HR\Http\Controllers\ShiftOverrideController;
use App\Modules\HR\Http\Controllers\InternetVoucherController;

use App\Modules\HR\Http\Controllers\Reports\AttendanceReportController;
use App\Modules\HR\Http\Controllers\LeavePassController;
use App\Modules\HR\Http\Controllers\VisitorController;
use App\Modules\HR\Http\Controllers\PublicInternshipController;
use App\Modules\HR\Http\Controllers\InternshipDashboardController;

/*
|--------------------------------------------------------------------------
| HR Module API Routes
|--------------------------------------------------------------------------
*/

Route::post('hr/visitors/public-register', [VisitorController::class, 'publicStore']);
Route::get('hr/visitors/search-hosts', [VisitorController::class, 'searchHosts']);

Route::get('hr/visitors/check-status/{token}', [\App\Modules\HR\Http\Controllers\VisitorController::class, 'checkStatus']);
Route::post('hr/internship/apply', [PublicInternshipController::class, 'store'])->middleware('throttle:5,1');
Route::post('hr/internship/track', [PublicInternshipController::class, 'track'])->middleware('throttle:10,1');
Route::post('hr/internship/update/{id}', [PublicInternshipController::class, 'update'])->middleware('throttle:5,1');

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
    // 🌟 المسار الجديد: كشف الحساب المالي للموظف (يجب أن يكون قبل apiResource لتجنب تداخل الروابط)
    Route::get('employees/{id}/financial-statement', [EmployeeController::class, 'getFinancialStatement']);

    Route::apiResource('employees', EmployeeController::class);

    // -------------------------------------------
    // إدارة المتدربين والتحويلات (Internship Management)
    // -------------------------------------------
    Route::get('internship-applications', [InternshipDashboardController::class, 'index']);
    Route::get('internship-applications/active-interns', [InternshipDashboardController::class, 'activeInterns']);
    Route::post('internship-applications/{id}/approve', [InternshipDashboardController::class, 'approve']);
    Route::post('internship-applications/{id}/reject', [InternshipDashboardController::class, 'reject']);
    Route::post('internship-applications/{id}/convert', [InternshipDashboardController::class, 'convert']);

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

    Route::apiResource('overtime-policies', OvertimePolicyController::class)
        ->parameters(['overtime-policies' => 'overtime_policy']);

    Route::apiResource('pay-groups', PayGroupController::class);
    Route::post('pay-periods/generate', [PayPeriodController::class, 'generate']);
    Route::apiResource('pay-periods', PayPeriodController::class);

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

    // -------------------------------------------
    // الجدولة والورديات (Scheduling & Shifts)
    // -------------------------------------------
    // إدارة الورديات الأساسية
    Route::apiResource('shifts', ShiftController::class);

    // قوالب الجدولة (تحديد دورات العمل)
    Route::apiResource('working-schedules', WorkingScheduleController::class);

    // الاستثناءات التقويمية (الطوارئ والعطلات الرسمية)
    Route::apiResource('calendar-exceptions', CalendarExceptionController::class);

    Route::apiResource('shift-overrides', ShiftOverrideController::class);
    // -------------------------------------------

    // الحضور والانصراف
    Route::apiResource('attendance-logs', AttendanceLogController::class);
    Route::post('attendance-logs/scan', [AttendanceLogController::class, 'scanBarcode']);
    Route::get('reports/attendance-summary', AttendanceReportController::class);

    Route::prefix('team-attendance')->name('team_attendance.')->group(function () {
        Route::get('/', [ManagerAttendanceController::class, 'index'])->name('index');
        Route::post('/override', [ManagerAttendanceController::class, 'override'])->name('override');
    });


    Route::get('leave-passes/emergency-muster', [LeavePassController::class, 'emergencyMusterList']);

    // 🛡️ [المسار المستحدث والمنسي] المسح المؤتمت والذكي لقارئ الـ QR والـ Barcode عند البوابة الخارجية للأمن
    Route::post('leave-passes/scan-gate-code', [LeavePassController::class, 'scanGateCode']);

    // حركات الاعتماد اليدوي للمشرفين والضغط البصري للحرس
    Route::post('leave-passes/{leave_pass}/approve', [LeavePassController::class, 'approve']);
    Route::post('leave-passes/{leave_pass}/gate-check', [LeavePassController::class, 'gateCheck']);

    // العمليات القياسية (CRUD) للأذونات
    Route::apiResource('leave-passes', LeavePassController::class);

    // المدخلات المالية المتغيرة (حوافز/خصومات)
    Route::apiResource('payroll-inputs', PayrollInputController::class);


    // -------------------------------------------
    // أكواد الإنترنت (Internet Vouchers)
    // -------------------------------------------
    Route::prefix('internet-vouchers')->group(function () {
        Route::get('/', [InternetVoucherController::class, 'index']);
        Route::post('/import', [InternetVoucherController::class, 'import']);
        Route::post('/assign-manually', [InternetVoucherController::class, 'assignManually']);
    });

    // ===========================================
    // 5. معالجة الرواتب (Payroll Processing)
    // ===========================================
    // 🌟 تم تجميع كل مسارات الرواتب في Group واحد لترتيب الكود
    Route::prefix('payroll')->name('payroll.')->group(function () {

        // مسارات الاستعلامات والجلب
        Route::get('batches', [PayrollController::class, 'getBatches']);
        Route::post('summary', [PayrollController::class, 'getSummary']);
        Route::get('processed-employees', [PayrollController::class, 'getProcessedEmployees']);
        Route::get('batches/{batchId}/export-bank', [PayrollController::class, 'exportBankFile']);

        // معاينة الراتب (Preview)
        Route::post('preview', [PayrollController::class, 'preview'])
            ->middleware('can:hr.payroll.view');

        // اعتماد وترحيل الرواتب (Post Batch)
        Route::post('post-batch', [PayrollController::class, 'postBatch'])
            ->middleware('can:hr.payroll.post');
    });



    // ===========================================
    // 6. إدارة الزوار وبوابات الأمن (Visitor Management)
    // ===========================================
    Route::apiResource('visitors', VisitorController::class);
    Route::post('visitors/gate/check-in', [VisitorController::class, 'checkIn']);
    Route::post('visitors/gate/check-out', [VisitorController::class, 'checkOut']);

});



