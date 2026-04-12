<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Modules\HR\Models\SalaryStructure;
use App\Modules\HR\Http\Requests\SalaryStructure\SalaryStructureRequest;
use App\Modules\HR\Http\Resources\SalaryStructureResource;

class SalaryStructureController extends Controller
{
    public function __construct()
    {
        // لكي يعمل هذا السطر، يجب أن يكون المتغير في الدوال هو $salary_structure
        $this->authorizeResource(SalaryStructure::class, 'salary_structure');
    }

    public function index(): JsonResponse
    {
        $structures = SalaryStructure::with('rules')->orderBy('id', 'desc')->get();
        return response()->json(SalaryStructureResource::collection($structures));
    }

    public function store(SalaryStructureRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $structure = SalaryStructure::create($request->safe()->except(['rules']));

            if ($request->has('rules')) {
                $syncData = [];
                foreach ($request->rules as $item) {
                    $syncData[$item['rule_id']] = ['sequence' => $item['sequence']];
                }
                $structure->rules()->sync($syncData);
            }

            return response()->json([
                'message' => 'تم إنشاء هيكل الرواتب بنجاح',
                'data' => new SalaryStructureResource($structure->load('rules'))
            ], 201);
        });
    }

    /**
     * تم تحويل $id إلى Route Model Binding لتعمل الـ Policy تلقائياً
     */
    public function show(SalaryStructure $salaryStructure): JsonResponse
    {
        return response()->json(new SalaryStructureResource($salaryStructure->load('rules')));
    }

    public function update(SalaryStructureRequest $request, SalaryStructure $salaryStructure): JsonResponse
    {
        return DB::transaction(function () use ($request, $salaryStructure) {
            // تحديث البيانات الأساسية
            $salaryStructure->update($request->safe()->except(['rules']));

            // تحديث القواعد المرتبطة
            if ($request->has('rules')) {
                $syncData = [];
                foreach ($request->rules as $item) {
                    $syncData[$item['rule_id']] = ['sequence' => $item['sequence']];
                }
                $salaryStructure->rules()->sync($syncData);
            }

            return response()->json([
                'message' => 'تم تحديث هيكل الرواتب بنجاح',
                'data' => new SalaryStructureResource($salaryStructure->load('rules'))
            ]);
        });
    }

    public function destroy(SalaryStructure $salaryStructure): JsonResponse
    {
        // فحص الارتباط بعقود الموظفين
        if ($salaryStructure->contracts()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف الهيكل لارتباطه بعقود موظفين سارية.'
            ], 422);
        }

        $salaryStructure->delete();

        return response()->json([
            'message' => 'تم أرشفة هيكل الرواتب بنجاح'
        ]);
    }
}
