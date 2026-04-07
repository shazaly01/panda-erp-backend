<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\PayrollInput;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Http\Requests\Payroll\StorePayrollInputRequest;
use App\Modules\HR\Http\Requests\Payroll\UpdatePayrollInputRequest;
use App\Modules\HR\Http\Resources\PayrollInputResource;
use App\Modules\HR\Services\PayrollInputService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class PayrollInputController extends Controller
{
    /**
     * حقن الخدمة وتفعيل السياسة الموحدة
     */
    public function __construct(private readonly PayrollInputService $payrollInputService)
    {
        /**
         * تفعيل السياسة (PayrollInputPolicy)
         * - يربط العمليات (index, store, show, update, destroy) تلقائياً
         * - تأكد أن مسمى المتغير في الراوت هو 'payroll_input'
         */
        $this->authorizeResource(PayrollInput::class, 'payroll_input');
    }

    /**
     * عرض قائمة المدخلات المالية (الحوافز والخصومات)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // تم الفحص تلقائياً عبر PayrollInputPolicy@viewAny

        $user = Auth::user();
        $query = PayrollInput::with(['employee', 'creator']);

        // فلترة بوابة الخدمة الذاتية (ESS)
        // إذا لم يكن لديه صلاحية الإدارة أو العرض العام، يرى مدخلاته الشخصية فقط
        if (!$user->can('hr.payroll_inputs.manage') && !$user->can('hr.payroll_inputs.view') && $user->employee_id) {
            $query->where('employee_id', $user->employee_id);
        }

        // إمكانية الفلترة (حسب الموظف، النوع، أو الحالة)
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return PayrollInputResource::collection($query->orderByDesc('date')->paginate(20));
    }

    /**
     * إدخال حركة مالية جديدة (مكافأة أو خصم)
     */
    public function store(StorePayrollInputRequest $request): JsonResponse
    {
        // تم الفحص تلقائياً عبر PayrollInputPolicy@create

        $data = $request->validated();
        $employee = Employee::findOrFail($data['employee_id']);

        try {
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
        // تم الفحص تلقائياً عبر PayrollInputPolicy@view
        return new PayrollInputResource($payrollInput->load(['employee', 'creator']));
    }

    /**
     * تعديل الحركة المالية (مسموح فقط إذا لم تُرحّل)
     */
    public function update(UpdatePayrollInputRequest $request, PayrollInput $payrollInput): JsonResponse
    {
        // تم الفحص تلقائياً عبر PayrollInputPolicy@update
        // السياسة ستمنع التعديل إذا كانت الحالة is_processed = true

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
        // تم الفحص تلقائياً عبر PayrollInputPolicy@delete
        $payrollInput->delete();

        return response()->json(['message' => 'تم إلغاء الحركة المالية بنجاح.'], 200);
    }
}
