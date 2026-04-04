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
        // دعم البحث والفلترة
        $query = Employee::with(['department', 'position']);

        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->has('search')) {
            $query->where('full_name', 'like', "%{$request->search}%")
                  ->orWhere('employee_number', 'like', "%{$request->search}%");
        }

        $employees = $query->paginate(20);

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
        // تحميل العلاقات المهمة، بما فيها العقد الحالي
        $employee->load(['department', 'position', 'manager', 'currentContract.salaryStructure']);

        return response()->json([
            'data' => new EmployeeResource($employee)
        ]);
    }

    // ... Update & Destroy functions (similarly implemented)
}
