<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Contract;
use App\Modules\HR\Http\Resources\ContractResource;
use App\Modules\HR\Http\Requests\Contract\StoreContractRequest;
use App\Modules\HR\Http\Requests\Contract\UpdateContractRequest;
use App\Modules\HR\Enums\SalaryFrequency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContractController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Contract::class, 'contract');
    }

    /**
     * عرض قائمة العقود مع دعم الفلترة
     */
    public function index(Request $request): JsonResponse
    {
        // 🚀 التعديل هنا: إضافة overtimePolicy للتحميل المسبق
        $query = Contract::with(['employee', 'salaryStructure', 'overtimePolicy']);

        if ($request->filled('salary_frequency')) {
            $query->where('salary_frequency', $request->salary_frequency);
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        $contracts = $query->orderBy('id', 'desc')->get();
        return response()->json(ContractResource::collection($contracts));
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
            // 🚀 التعديل هنا: تحميل overtimePolicy
            'data' => new ContractResource($contract->load(['employee', 'salaryStructure', 'overtimePolicy'])),
        ], 201);
    }

    public function show(Contract $contract): JsonResponse
    {
        // 🚀 التعديل هنا
        return response()->json(new ContractResource($contract->load(['employee', 'salaryStructure', 'overtimePolicy'])));
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
            // 🚀 التعديل هنا
            'data' => new ContractResource($contract->load(['employee', 'salaryStructure', 'overtimePolicy'])),
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
            // 🚀 التعديل هنا
            'data' => new ContractResource($contract->load(['employee', 'salaryStructure', 'overtimePolicy']))
        ]);
    }

    public function destroy(Contract $contract): JsonResponse
    {
        $contract->delete();
        return response()->json(['message' => 'تم أرشفة العقد بنجاح']);
    }
}
