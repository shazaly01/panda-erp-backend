<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Http\Resources\EmployeeResource;
use App\Modules\HR\Http\Requests\Employee\StoreEmployeeRequest;
use App\Modules\HR\Http\Requests\Employee\UpdateEmployeeRequest; // 🌟 تم التفعيل
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Employee::class, 'employee');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['department', 'position']);

        // 1. فلتر الإدارة
        if ($request->filled('department_id') && is_numeric($request->department_id)) {
            $query->where('department_id', $request->department_id);
        }

        // 2. فلتر المسمى الوظيفي
        if ($request->filled('position_id') && is_numeric($request->position_id)) {
            $query->where('position_id', $request->position_id);
        }

        // 3. فلتر الحالة
        if ($request->filled('status') && $request->status !== 'null' && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // 4. فلتر البحث المجمع
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_number', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $employees = $query->latest('id')->paginate(20);

        return EmployeeResource::collection($employees)->response();
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create($request->validated());

        return response()->json([
            'message' => 'تم إضافة الموظف بنجاح',
            'data' => new EmployeeResource($employee->load(['department', 'position'])),
        ], 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        // تحميل العلاقات المهمة
        $employee->load([
            'department',
            'position',
            'manager',
            'currentContract.salaryStructure',
            // 'employeeShifts.shift' // ⚠️ تنبيه: تأكد من وجود هذه العلاقة في الموديل
        ]);

        return response()->json([
            'data' => new EmployeeResource($employee)
        ]);
    }

    // 🌟 إضافة دالة التعديل
    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث بيانات الموظف بنجاح',
            'data' => new EmployeeResource($employee->load(['department', 'position'])),
        ]);
    }

    // 🌟 إضافة دالة الحذف (مع حماية أمنية)
    public function destroy(Employee $employee): JsonResponse
    {
        // حماية النظام: لا يمكن حذف موظف لديه عقد عمل نشط
        if ($employee->currentContract()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف الموظف لوجود عقد عمل نشط. يرجى إنهاء العقد أولاً.'
            ], 422);
        }

        $employee->delete(); // Soft Delete

        return response()->json([
            'message' => 'تم أرشفة الموظف بنجاح'
        ]);
    }
}
