<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Contract;
use App\Modules\HR\Http\Resources\ContractResource;
use App\Modules\HR\Http\Requests\Contract\StoreContractRequest;
use App\Modules\HR\Http\Requests\Contract\UpdateContractRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ContractController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Contract::class, 'contract');
    }

    public function index(): JsonResponse
    {
        $contracts = Contract::with(['employee', 'salaryStructure'])->orderBy('id', 'desc')->get();
        return response()->json(ContractResource::collection($contracts));
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('attachment')) {
            // تخزين الملف في الـ public disk
            $data['attachment_path'] = $request->file('attachment')->store('contracts', 'public');
        }

        // إغلاق أي عقد نشط سابق
        Contract::where('employee_id', $data['employee_id'])
                ->where('is_active', true)
                ->update(['is_active' => false, 'end_date' => now()]);

        $contract = Contract::create(array_merge($data, ['is_active' => true]));

        return response()->json([
            'message' => 'تم إنشاء العقد وتفعيله بنجاح',
            'data' => new ContractResource($contract->load(['employee', 'salaryStructure'])),
        ], 201);
    }

    public function show(Contract $contract): JsonResponse
    {
        return response()->json(new ContractResource($contract->load(['employee', 'salaryStructure'])));
    }

    public function update(UpdateContractRequest $request, Contract $contract): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('attachment')) {
            // حذف المرفق القديم إن وجد
            if ($contract->attachment_path) {
                Storage::disk('public')->delete($contract->attachment_path);
            }
            $data['attachment_path'] = $request->file('attachment')->store('contracts', 'public');
        }

        $contract->update($data);

        return response()->json([
            'message' => 'تم تحديث العقد بنجاح',
            'data' => new ContractResource($contract->load(['employee', 'salaryStructure'])),
        ]);
    }

    /**
     * دالة مخصصة لإنهاء خدمة الموظف أو إيقاف عقده
     */
 // في ContractController.php
public function terminate(Contract $contract): JsonResponse
{
    $this->authorize('update', $contract);

    $contract->update([
        'is_active' => false,
        'end_date' => now()
    ]);

    // 🌟 أضف التحميل هنا أيضاً لكي لا يفقد الـ Vue اسم الموظف بعد التحديث
    return response()->json([
        'message' => 'تم إنهاء العقد بنجاح',
        'data' => new ContractResource($contract->load(['employee', 'salaryStructure']))
    ]);
}

    public function destroy(Contract $contract): JsonResponse
    {
        $contract->delete();
        return response()->json(['message' => 'تم أرشفة العقد بنجاح']);
    }
}
