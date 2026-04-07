<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Shift;
use App\Modules\HR\Models\EmployeeShift;
use App\Modules\HR\Http\Requests\Shift\StoreShiftRequest;
use App\Modules\HR\Http\Requests\Shift\UpdateShiftRequest;
use App\Modules\HR\Http\Requests\Shift\AssignEmployeeShiftRequest;
use App\Modules\HR\Http\Resources\ShiftResource;
use App\Modules\HR\Http\Resources\EmployeeShiftResource;
use App\Modules\HR\Services\ShiftService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    /**
     * حقن الخدمة وتفعيل السياسة للوردية
     */
    public function __construct(private readonly ShiftService $shiftService)
    {
        /**
         * تفعيل ShiftPolicy للعمليات الأساسية
         * ملاحظة: المتغير في الراوت يجب أن يكون 'shift'
         */
        $this->authorizeResource(Shift::class, 'shift');
    }

    // ==========================================
    // 1. إدارة الورديات الأساسية (Shifts)
    // ==========================================

    /**
     * عرض قائمة الورديات
     */
    public function index(): AnonymousResourceCollection
    {
        // تم الفحص تلقائياً عبر ShiftPolicy@viewAny
        return ShiftResource::collection(Shift::latest()->paginate(15));
    }

    /**
     * تعريف وردية جديدة
     */
    public function store(StoreShiftRequest $request): ShiftResource
    {
        // تم الفحص تلقائياً عبر ShiftPolicy@create
        $shift = Shift::create($request->validated());
        return new ShiftResource($shift);
    }

    /**
     * عرض بيانات وردية محددة
     */
    public function show(Shift $shift): ShiftResource
    {
        // تم الفحص تلقائياً عبر ShiftPolicy@view
        return new ShiftResource($shift);
    }

    /**
     * تحديث بيانات الوردية
     */
    public function update(UpdateShiftRequest $request, Shift $shift): ShiftResource
    {
        // تم الفحص تلقائياً عبر ShiftPolicy@update
        $shift->update($request->validated());
        return new ShiftResource($shift);
    }

    /**
     * حذف الوردية
     */
    public function destroy(Shift $shift): JsonResponse
    {
        // تم الفحص تلقائياً عبر ShiftPolicy@delete

        if ($shift->employeeShifts()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف وردية مرتبط بها موظفون حالياً.'
            ], 422);
        }

        $shift->delete();
        return response()->json(['message' => 'تم حذف الوردية بنجاح.'], 200);
    }

    // ==========================================
    // 2. إدارة تعيينات الموظفين (Employee Shifts)
    // ==========================================

    /**
     * جلب سجل الورديات لموظف معين
     */
    public function employeeShifts(int $employeeId): AnonymousResourceCollection
    {
        // دوال مخصصة: نحتاج لفحص الصلاحية يدوياً
        $this->authorize('viewAny', Shift::class);

        $employeeShifts = EmployeeShift::with(['employee', 'shift'])
            ->where('employee_id', $employeeId)
            ->orderByDesc('start_date')
            ->get();

        return EmployeeShiftResource::collection($employeeShifts);
    }

    /**
     * تعيين موظف على وردية جديدة (Business Logic)
     */
    public function assignEmployee(AssignEmployeeShiftRequest $request): EmployeeShiftResource|JsonResponse
    {
        // نستخدم صلاحية الـ create لعملية التعيين أيضاً
        $this->authorize('create', Shift::class);

        try {
            $employeeShift = $this->shiftService->assignEmployeeToShift($request->validated());

            return new EmployeeShiftResource(
                $employeeShift->load(['employee', 'shift'])
            );
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطأ أثناء تعيين الوردية: ' . $e->getMessage()
            ], 422); // استخدام 422 للـ Validation/Business errors أفضل من 500
        }
    }
}
