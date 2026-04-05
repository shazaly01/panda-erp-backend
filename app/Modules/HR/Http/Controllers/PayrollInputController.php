<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\PayrollInput;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Http\Requests\Payroll\StorePayrollInputRequest;
use App\Modules\HR\Http\Requests\Payroll\UpdatePayrollInputRequest;
use App\Modules\HR\Http\Resources\PayrollInputResource; // المسار المباشر
use App\Modules\HR\Services\PayrollInputService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class PayrollInputController extends Controller
{
    public function __construct(private readonly PayrollInputService $payrollInputService)
    {
    }

    /**
     * عرض قائمة المدخلات المالية (الحوافز والخصومات)
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PayrollInput::class);

        $user = Auth::user();
        $query = PayrollInput::with(['employee', 'creator']);

        // فلترة بوابة الخدمة الذاتية: الموظف يرى مكافآته وخصوماته فقط
        if (!$user->hasPermissionTo('hr.payroll_inputs.view') && $user->employee_id) {
            $query->where('employee_id', $user->employee_id);
        }

        // ترتيب تنازلي حسب التاريخ ليعرض أحدث الحركات أولاً
        return PayrollInputResource::collection($query->orderByDesc('date')->paginate(20));
    }

    /**
     * إدخال حركة مالية جديدة (مكافأة أو خصم)
     */
    public function store(StorePayrollInputRequest $request): JsonResponse
    {
        $this->authorize('create', PayrollInput::class);

        $data = $request->validated();
        $employee = Employee::findOrFail($data['employee_id']);

        try {
            // نستخدم الخدمة لتسجيل الحركة المالية لضمان تطبيق أي قواعد إضافية (Business Logic)
            $payrollInput = $this->payrollInputService->addInput(
                $employee,
                $data['type'],
                (float) $data['amount'],
                $data['date'],
                $data['reason'] ?? null,
                Auth::id()
            );

            return response()->json([
                'message' => 'تم تسجيل الحركة المالية بنجاح.',
                'data' => new PayrollInputResource($payrollInput->load(['employee', 'creator']))
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * عرض تفاصيل حركة مالية محددة
     */
    public function show(PayrollInput $payrollInput): PayrollInputResource
    {
        $this->authorize('view', $payrollInput);

        return new PayrollInputResource($payrollInput->load(['employee', 'creator']));
    }

    /**
     * تعديل الحركة المالية (مسموح فقط إذا لم تُرحّل في مسير الرواتب)
     */
    public function update(UpdatePayrollInputRequest $request, PayrollInput $payrollInput): JsonResponse
    {
        // السياسة (PayrollInputPolicy) ستمنع التعديل آلياً إذا كان is_processed = true
        $this->authorize('update', $payrollInput);

        $payrollInput->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث الحركة المالية بنجاح.',
            'data' => new PayrollInputResource($payrollInput->load(['employee', 'creator']))
        ], 200);
    }

    /**
     * إلغاء/حذف الحركة المالية
     */
    public function destroy(PayrollInput $payrollInput): JsonResponse
    {
        // السياسة ستمنع الحذف آلياً إذا كانت الحركة قد دخلت في مسير رواتب
        $this->authorize('delete', $payrollInput);

        $payrollInput->delete();

        return response()->json(['message' => 'تم إلغاء الحركة المالية بنجاح.'], 200);
    }
}
