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
    /**
     * إعداد الحماية والصلاحيات للمتحكم
     */
   public function __construct()
    {
        // استخدام نفس نمط البنوك: تفعيل السياسة (Policy)
        // لاحظ أن الاسم في الراوت يجب أن يكون salary_structure
        $this->authorizeResource(SalaryStructure::class, 'salary_structure');
    }

    public function index(): JsonResponse
    {
        // index الآن محمية تلقائياً بـ viewAny في السياسة
        $structures = SalaryStructure::with('rules')->orderBy('id', 'desc')->get();
        return response()->json(SalaryStructureResource::collection($structures));
    }

    /**
     * إنشاء هيكل جديد وربط القواعد به في عملية واحدة (Atomic)
     */
    public function store(SalaryStructureRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            // 1. إنشاء الهيكل (تجاهل مصفوفة القواعد مؤقتاً)
            $structure = SalaryStructure::create($request->safe()->except(['rules']));

            // 2. ربط القواعد بالجدول الوسيط مع حفظ الترتيب (sequence)
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
     * عرض تفاصيل هيكل معين
     */
    public function show($id): JsonResponse
    {
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

            // 2. تحديث علاقات القواعد (حذف القديم وإضافة الجديد)
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
     * حذف الهيكل (أرشفة عبر Soft Delete)
     */
    public function destroy($id): JsonResponse
    {
        $structure = SalaryStructure::findOrFail($id);

        // ملاحظة: يمكنك إضافة فحص هنا لمنع الحذف إذا كان الهيكل مرتبطاً بعقود نشطة
        if ($structure->contracts()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف الهيكل لارتباطه بعقود موظفين سارية.'
            ], 422);
        }

        $structure->delete();

        return response()->json([
            'message' => 'تم أرشفة هيكل الرواتب بنجاح'
        ]);
    }
}
