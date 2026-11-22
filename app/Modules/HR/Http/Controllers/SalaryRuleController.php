<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Http\Requests\SalaryRule\SalaryRuleRequest;
use App\Modules\HR\Http\Resources\SalaryRuleResource;
use App\Modules\HR\Models\SalaryRule;
use Illuminate\Http\JsonResponse;

class SalaryRuleController extends Controller
{
    /**
     * تفعيل السياسة الموحدة وربطها بالصلاحيات
     */
    public function __construct()
    {
        $this->authorizeResource(SalaryRule::class, 'salary_rule');
    }

    /**
     * عرض جميع قواعد الراتب مرتبة حسب الفئة (بدلات / خصومات / مساهمات شركة)
     */
    public function index(): JsonResponse
    {
        $rules = SalaryRule::query()
            ->with([
                'accountMapping.account',
                'creditAccountMapping.account',
            ])
            ->orderBy('category')
            ->orderBy('id')
            ->get();

        return response()->json(SalaryRuleResource::collection($rules));
    }

    /**
     * إنشاء قاعدة راتب جديدة
     */
    public function store(SalaryRuleRequest $request): JsonResponse
    {
        $salaryRule = SalaryRule::create($request->validated());
        $salaryRule->load([
            'accountMapping.account',
            'creditAccountMapping.account',
        ]);

        return response()->json([
            'message' => 'تم إنشاء قاعدة الراتب بنجاح',
            'data'    => new SalaryRuleResource($salaryRule),
        ], 201);
    }

    /**
     * عرض تفاصيل قاعدة معينة
     */
    public function show(SalaryRule $salaryRule): JsonResponse
    {
        $salaryRule->load([
            'accountMapping.account',
            'creditAccountMapping.account',
        ]);

        return response()->json(new SalaryRuleResource($salaryRule));
    }

    /**
     * تحديث بيانات القاعدة
     */
    public function update(SalaryRuleRequest $request, SalaryRule $salaryRule): JsonResponse
    {
        $salaryRule->update($request->validated());
        $salaryRule->load([
            'accountMapping.account',
            'creditAccountMapping.account',
        ]);

        return response()->json([
            'message' => 'تم تحديث قاعدة الراتب بنجاح',
            'data'    => new SalaryRuleResource($salaryRule),
        ]);
    }

    /**
     * حذف القاعدة (أرشفة)
     */
    public function destroy(SalaryRule $salaryRule): JsonResponse
    {
        $salaryRule->delete();

        return response()->json([
            'message' => 'تم أرشفة قاعدة الراتب بنجاح',
        ]);
    }
}