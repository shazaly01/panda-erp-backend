<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Contract;
use App\Modules\HR\Http\Resources\ContractResource;
use App\Modules\HR\Http\Requests\Contract\StoreContractRequest;
use Illuminate\Http\JsonResponse;

class ContractController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Contract::class, 'contract');
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
        $data = $request->validated();

        // رفع الملف إذا وجد
        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('contracts');
        }

        // إغلاق أي عقد نشط سابق لهذا الموظف (لضمان وجود عقد نشط واحد)
        Contract::where('employee_id', $data['employee_id'])
                ->where('is_active', true)
                ->update(['is_active' => false, 'end_date' => now()]);

        $contract = Contract::create(array_merge($data, ['is_active' => true]));

        return response()->json([
            'message' => 'تم إنشاء العقد وتفعيله بنجاح',
            'data' => new ContractResource($contract),
        ], 201);
    }

    // ... يمكن إضافة دوال التجديد (Renew) والإنهاء (Terminate)
}
