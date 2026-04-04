<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Modules\HR\Models\SalaryRule;
// 1. استدعاء الـ Request من المسار الصحيح (داخل المجلد الفرعي)
use App\Modules\HR\Http\Requests\SalaryRule\SalaryRuleRequest;
// 2. استدعاء الـ Resource لتنسيق المخرجات
use App\Modules\HR\Http\Resources\SalaryRuleResource;

class SalaryRuleController extends Controller
{
    /**
     * عرض جميع قواعد الراتب
     */
    public function index(): JsonResponse
    {
        $this->authorizePermission('hr.settings.manage');

        $rules = SalaryRule::query()
            ->orderBy('category') // ترتيب: بدلات ثم خصومات
            ->orderBy('id')
            ->get();

        // إرجاع البيانات باستخدام Resource Collection
        return response()->json(SalaryRuleResource::collection($rules));
    }

    /**
     * إنشاء قاعدة جديدة
     */
    public function store(SalaryRuleRequest $request): JsonResponse
    {
        // لا نحتاج للتحقق من الصلاحية هنا لأن الـ Request تكفل بذلك

        $salaryRule = SalaryRule::create($request->validated());

        return response()->json([
            'message' => 'تم إنشاء قاعدة الراتب بنجاح',
            'data' => new SalaryRuleResource($salaryRule)
        ], 201);
    }

    /**
     * عرض تفاصيل قاعدة معينة
     */
    public function show($id): JsonResponse
    {
        $this->authorizePermission('hr.settings.manage');

        $salaryRule = SalaryRule::findOrFail($id);

        return response()->json(new SalaryRuleResource($salaryRule));
    }

    /**
     * تحديث قاعدة موجودة
     */
    public function update(SalaryRuleRequest $request, $id): JsonResponse
    {
        $salaryRule = SalaryRule::findOrFail($id);

        $salaryRule->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث قاعدة الراتب بنجاح',
            'data' => new SalaryRuleResource($salaryRule)
        ]);
    }

    /**
     * حذف قاعدة (أرشفة - Soft Delete)
     */
    public function destroy($id): JsonResponse
    {
        $this->authorizePermission('hr.settings.manage');

        $salaryRule = SalaryRule::findOrFail($id);

        // يمكن إضافة تحقق هنا: هل القاعدة مستخدمة في عقود نشطة؟
        // لكن للآن سنكتفي بالحذف الناعم (Soft Delete) الموجود في الموديل
        $salaryRule->delete();

        return response()->json([
            'message' => 'تم أرشفة قاعدة الراتب بنجاح'
        ]);
    }

    /**
     * دالة مساعدة للتحقق من الصلاحيات
     */
    protected function authorizePermission(string $permission): void
    {
        if (! auth()->user()->hasPermissionTo($permission)) {
            abort(403, 'ليس لديك صلاحية للقيام بهذا الإجراء.');
        }
    }
}
