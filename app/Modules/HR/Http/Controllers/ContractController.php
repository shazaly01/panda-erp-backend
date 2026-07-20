<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Contract;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Http\Resources\ContractResource;
use App\Modules\HR\Http\Resources\EmployeeResource;
use App\Modules\HR\Http\Requests\Contract\StoreContractRequest;
use App\Modules\HR\Http\Requests\Contract\UpdateContractRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContractController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Contract::class, 'contract');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Contract::with(['employee', 'salaryStructure', 'overtimePolicy', 'payGroup', 'workingSchedule']);

        // البحث باسم الموظف أو كوده الوظيفي
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        // الفلترة بـ ID الموظف مباشرة
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        // الفلترة باستخدام مجموعة الدفع
        if ($request->filled('pay_group_id')) {
            $query->where('pay_group_id', $request->pay_group_id);
        }

        // الفلترة بالعقود النشطة فقط
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        $contracts = $query->orderBy('id', 'desc')->get();
        return response()->json(ContractResource::collection($contracts));
    }

    /**
     * 🌟 دالة مخصصة لجهة العقود: تجلب الموظفين المتاحين لإنشاء عقد جديد لهم
     * (الذين ليس لديهم عقد نشط أو ليس لديهم عقود نهائياً)
     */
    public function availableEmployees(Request $request): JsonResponse
    {
        $this->authorize('create', Contract::class);

        $query = Employee::query();

        // إذا كان المطلوب فقط من ليس لديهم أي عقد نهائياً
        if ($request->boolean('without_any_contract')) {
            $query->whereDoesntHave('contracts');
        } else {
            // الافتراضي: الموظفين الذين ليس لديهم عقد نشط حالياً
            $query->whereDoesntHave('contracts', function ($q) {
                $q->where('is_active', true);
            });
        }

        // دعم البحث باسم الموظف أو رقمه الوظيفي
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        $employees = $query->with(['department', 'position'])->orderBy('full_name', 'asc')->get();

        return response()->json(EmployeeResource::collection($employees));
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('contracts', 'public');
        }

        Contract::where('employee_id', $data['employee_id'])
                ->where('is_active', true)
                ->update(['is_active' => false, 'end_date' => now()]);

        $contract = Contract::create(array_merge($data, ['is_active' => true]));

        return response()->json([
            'message' => 'تم إنشاء العقد وتفعيله بنجاح',
            'data' => new ContractResource($contract->load(['employee', 'salaryStructure', 'overtimePolicy', 'payGroup', 'workingSchedule'])),
        ], 201);
    }

    public function show(Contract $contract): JsonResponse
    {
        return response()->json(new ContractResource($contract->load(['employee', 'salaryStructure', 'overtimePolicy', 'payGroup', 'workingSchedule'])));
    }

    public function update(UpdateContractRequest $request, Contract $contract): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('attachment')) {
            if ($contract->attachment_path) {
                Storage::disk('public')->delete($contract->attachment_path);
            }
            $data['attachment_path'] = $request->file('attachment')->store('contracts', 'public');
        }

        $contract->update($data);

        return response()->json([
            'message' => 'تم تحديث العقد بنجاح',
            'data' => new ContractResource($contract->load(['employee', 'salaryStructure', 'overtimePolicy', 'payGroup', 'workingSchedule'])),
        ]);
    }

    public function terminate(Contract $contract): JsonResponse
    {
        $this->authorize('update', $contract);

        $contract->update([
            'is_active' => false,
            'end_date' => now()
        ]);

        return response()->json([
            'message' => 'تم إنهاء العقد بنجاح',
            'data' => new ContractResource($contract->load(['employee', 'salaryStructure', 'overtimePolicy', 'payGroup', 'workingSchedule']))
        ]);
    }

    public function destroy(Contract $contract): JsonResponse
    {
        $contract->delete();
        return response()->json(['message' => 'تم أرشفة العقد بنجاح']);
    }
}
