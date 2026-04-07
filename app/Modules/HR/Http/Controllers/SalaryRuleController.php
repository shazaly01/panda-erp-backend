<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Modules\HR\Models\SalaryRule;
use App\Modules\HR\Http\Requests\SalaryRule\SalaryRuleRequest;
use App\Modules\HR\Http\Resources\SalaryRuleResource;

class SalaryRuleController extends Controller
{
    /**
     * تفعيل السياسة الموحدة وربطها بالصلاحيات
     */
    public function __construct()
    {
        /**
         * تفعيل SalaryRulePolicy
         * - تأكد أن مسمى المتغير في الراوت هو 'salary_rule' ليعمل الربط التلقائي
         */
        $this->authorizeResource(SalaryRule::class, 'salary_rule');
    }

    /**
     * عرض جميع قواعد الراتب مرتبة حسب الفئة (بدلات/خصومات)
     */
    public function index(): JsonResponse
    {
        // تم التحقق تلقائياً عبر SalaryRulePolicy@viewAny
        $rules = SalaryRule::query()
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
        // تم التحقق تلقائياً عبر SalaryRulePolicy@create
        $salaryRule = SalaryRule::create($request->validated());

        return response()->json([
            'message' => 'تم إنشاء قاعدة الراتب بنجاح',
            'data' => new SalaryRuleResource($salaryRule)
        ], 201);
    }

    /**
     * عرض تفاصيل قاعدة معينة
     */
    public function show(SalaryRule $salaryRule): JsonResponse
    {
        // تم التحقق تلقائياً عبر SalaryRulePolicy@view
        return response()->json(new SalaryRuleResource($salaryRule));
    }

    /**
     * تحديث بيانات القاعدة
     */
    public function update(SalaryRuleRequest $request, SalaryRule $salaryRule): JsonResponse
    {
        // تم التحقق تلقائياً عبر SalaryRulePolicy@update
        $salaryRule->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث قاعدة الراتب بنجاح',
            'data' => new SalaryRuleResource($salaryRule)
        ]);
    }

    /**
     * حذف القاعدة (أرشفة)
     */
    public function destroy(SalaryRule $salaryRule): JsonResponse
    {
        // تم التحقق تلقائياً عبر SalaryRulePolicy@delete

        // ملاحظة: يفضل التحقق مستقبلاً إذا كانت القاعدة مرتبطة بهياكل رواتب نشطة
        $salaryRule->delete();

        return response()->json([
            'message' => 'تم أرشفة قاعدة الراتب بنجاح'
        ]);
    }
}
