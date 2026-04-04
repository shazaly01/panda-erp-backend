<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Modules\HR\Models\SalaryStructure;
// 1. استدعاء الـ Request من المسار الفرعي الصحيح
use App\Modules\HR\Http\Requests\SalaryStructure\SalaryStructureRequest;
// 2. استدعاء الـ Resource
use App\Modules\HR\Http\Resources\SalaryStructureResource;

class SalaryStructureController extends Controller
{
    /**
     * عرض جميع هياكل الرواتب مع قواعدها
     */
    public function index(): JsonResponse
    {
        $this->authorizePermission('hr.settings.manage');

        $structures = SalaryStructure::with('rules')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(SalaryStructureResource::collection($structures));
    }

    /**
     * إنشاء هيكل جديد وربط القواعد به
     */
    public function store(SalaryStructureRequest $request): JsonResponse
    {
        // نستخدم Transaction لضمان سلامة البيانات (Atomic Operation)
        return DB::transaction(function () use ($request) {

            // 1. إنشاء الهيكل الأساسي (الاسم، الوصف، الحالة)
            // نستخدم safe()->except('rules') لأخذ البيانات الخاصة بالجدول فقط وتجاهل مصفوفة القواعد
            $structure = SalaryStructure::create($request->safe()->except(['rules']));

            // 2. ربط القواعد (إذا وجدت في الطلب)
            if ($request->has('rules')) {
                $syncData = [];
                foreach ($request->rules as $item) {
                    // تحضير مصفوفة الـ Sync: المفتاح هو rule_id والقيمة هي الأعمدة الإضافية (sequence)
                    $syncData[$item['rule_id']] = ['sequence' => $item['sequence']];
                }
                // الحفظ في الجدول الوسيط structure_rules
                $structure->rules()->sync($syncData);
            }

            // إعادة تحميل العلاقات لعرضها في الرد
            return response()->json([
                'message' => 'تم إنشاء هيكل الرواتب بنجاح',
                'data' => new SalaryStructureResource($structure->load('rules'))
            ], 201);
        });
    }

    /**
     * عرض تفاصيل هيكل معين
     */
    public function show($id): JsonResponse
    {
        $this->authorizePermission('hr.settings.manage');

        $structure = SalaryStructure::with('rules')->findOrFail($id);

        return response()->json(new SalaryStructureResource($structure));
    }

    /**
     * تحديث الهيكل والقواعد المرتبطة به
     */
    public function update(SalaryStructureRequest $request, $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $structure = SalaryStructure::findOrFail($id);

            // 1. تحديث البيانات الأساسية
            $structure->update($request->safe()->except(['rules']));

            // 2. تحديث القواعد
            // دالة sync تقوم بحذف العلاقات القديمة وإضافة الجديدة تلقائياً
            if ($request->has('rules')) {
                $syncData = [];
                foreach ($request->rules as $item) {
                    $syncData[$item['rule_id']] = ['sequence' => $item['sequence']];
                }
                $structure->rules()->sync($syncData);
            }

            return response()->json([
                'message' => 'تم تحديث هيكل الرواتب بنجاح',
                'data' => new SalaryStructureResource($structure->load('rules'))
            ]);
        });
    }

    /**
     * حذف الهيكل (أرشفة)
     */
    public function destroy($id): JsonResponse
    {
        $this->authorizePermission('hr.settings.manage');

        $structure = SalaryStructure::findOrFail($id);

        // تحقق اختياري: يفضل عدم حذف الهيكل إذا كان مربوطاً بعقود موظفين سارية
        /*
        if ($structure->contracts()->exists()) {
             return response()->json(['message' => 'لا يمكن حذف الهيكل لارتباطه بموظفين'], 400);
        }
        */

        $structure->delete(); // Soft Delete

        return response()->json([
            'message' => 'تم أرشفة هيكل الرواتب بنجاح'
        ]);
    }

    /**
     * التحقق من الصلاحيات
     */
    protected function authorizePermission(string $permission): void
    {
        if (! auth()->user()->hasPermissionTo($permission)) {
            abort(403, 'ليس لديك صلاحية للقيام بهذا الإجراء.');
        }
    }
}
