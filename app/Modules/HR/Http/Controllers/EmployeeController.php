<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Http\Resources\EmployeeResource;
use App\Modules\HR\Http\Requests\Employee\StoreEmployeeRequest;
// use App\Modules\HR\Http\Requests\Employee\UpdateEmployeeRequest; // لا تنس إنشاءه
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

        // 1. فلتر الإدارة (نتأكد من أنه رقمي)
        if ($request->filled('department_id') && is_numeric($request->department_id)) {
            $query->where('department_id', $request->department_id);
        }

        // 2. فلتر المسمى الوظيفي (نتأكد من أنه رقمي)
        if ($request->filled('position_id') && is_numeric($request->position_id)) {
            $query->where('position_id', $request->position_id);
        }

        // 3. فلتر الحالة (نتأكد أنها ليست "الكل" وأنها موجودة فعلاً)
        if ($request->filled('status') && $request->status !== 'null' && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // 4. فلتر البحث المجمع
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                // استخدمنا full_name الصحيح الموجود في قاعدة بياناتك
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_number', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // ترتيب تنازلي لظهور الموظفين الجدد أولاً
        $employees = $query->latest('id')->paginate(20);

        return EmployeeResource::collection($employees)->response();
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create($request->validated());

        return response()->json([
            'message' => 'تم إضافة الموظف بنجاح',
            'data' => new EmployeeResource($employee),
        ], 201);
    }

   public function show(Employee $employee): JsonResponse
    {
        // تحميل العلاقات المهمة، بما فيها العقد الحالي والورديات لمعرفة الوردية النشطة
        $employee->load([
            'department',
            'position',
            'manager',
            'currentContract.salaryStructure',
            'employeeShifts.shift' // <--- الإضافة الدقيقة هنا
        ]);

        return response()->json([
            'data' => new EmployeeResource($employee)
        ]);
    }

    // ... Update & Destroy functions (similarly implemented)
}
