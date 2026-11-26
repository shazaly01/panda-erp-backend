<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Http\Requests\Disciplinary\ViolationTypeRequest;
use App\Modules\HR\Http\Resources\ViolationTypeResource;
use App\Modules\HR\Models\ViolationEscalationRule;
use App\Modules\HR\Models\ViolationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ViolationTypeController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(ViolationType::class, 'violation_type');
    }

    /**
     * عرض جميع اللوائح وأنواع المخالفات مع سلالم تدرجها العقابي
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ViolationType::query()
            ->with('escalationRules')
            ->withCount('disciplinaryActions');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('detection_mode')) {
            $query->where('detection_mode', $request->detection_mode);
        }

        if ($request->has('is_active') && $request->is_active !== '' && $request->is_active !== null) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $violationTypes = $query->orderBy('id')->get();

        return ViolationTypeResource::collection($violationTypes);
    }

    /**
     * إنشاء لائحة مخالفة جديدة مع درجات سلمها العقابي
     */
    public function store(ViolationTypeRequest $request): JsonResponse
    {
        $violationType = DB::transaction(function () use ($request): ViolationType {
            $data = $request->validated();
            $steps = $data['steps'];
            unset($data['steps']);

            $type = ViolationType::create($data);

            foreach ($steps as $step) {
                ViolationEscalationRule::create([
                    'violation_type_id' => $type->id,
                    'step_order'        => (int) $step['step_order'],
                    'action_type'       => $step['action_type'],
                    'deduction_type'    => $step['deduction_type'],
                    'deduction_value'   => $step['deduction_value'] ?? 0,
                    'suspension_days'   => $step['suspension_days'] ?? 0,
                    'validity_months'   => (int) $step['validity_months'],
                    'notes'             => $step['notes'] ?? null,
                ]);
            }

            return $type->load('escalationRules');
        });

        return response()->json([
            'message' => 'تم إنشاء لائحة المخالفة وسلم التدرج العقابي بنجاح.',
            'data'    => new ViolationTypeResource($violationType),
        ], 201);
    }

    /**
     * عرض تفاصيل لائحة محددة
     */
    public function show(ViolationType $violationType): ViolationTypeResource
    {
        $violationType->load('escalationRules')->loadCount('disciplinaryActions');

        return new ViolationTypeResource($violationType);
    }

    /**
     * تحديث بيانات اللائحة ومزامنة درجات سلمها العقابي
     */
    public function update(ViolationTypeRequest $request, ViolationType $violationType): JsonResponse
    {
        $updatedType = DB::transaction(function () use ($request, $violationType): ViolationType {
            $data = $request->validated();
            $steps = $data['steps'];
            unset($data['steps']);

            $violationType->update($data);

            $stepOrders = [];
            foreach ($steps as $step) {
                $stepOrder = (int) $step['step_order'];
                $stepOrders[] = $stepOrder;

                ViolationEscalationRule::updateOrCreate(
                    [
                        'violation_type_id' => $violationType->id,
                        'step_order'        => $stepOrder,
                    ],
                    [
                        'action_type'     => $step['action_type'],
                        'deduction_type'  => $step['deduction_type'],
                        'deduction_value' => $step['deduction_value'] ?? 0,
                        'suspension_days' => $step['suspension_days'] ?? 0,
                        'validity_months' => (int) $step['validity_months'],
                        'notes'           => $step['notes'] ?? null,
                    ]
                );
            }

            ViolationEscalationRule::where('violation_type_id', $violationType->id)
                ->whereNotIn('step_order', $stepOrders)
                ->delete();

            return $violationType->fresh(['escalationRules']);
        });

        return response()->json([
            'message' => 'تم تحديث لائحة المخالفة وسلم التدرج العقابي بنجاح.',
            'data'    => new ViolationTypeResource($updatedType),
        ]);
    }

    /**
     * حذف لائحة مخالفة (يُمنع الحذف إذا كانت مرتبطة بسجلات جزاءات فعلية)
     */
    public function destroy(ViolationType $violationType): JsonResponse
    {
        if ($violationType->disciplinaryActions()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف هذه اللائحة لوجود مخالفات مسجلة عليها للموظفين؛ يمكنك إيقاف تفعيلها بدلاً من ذلك.',
            ], 422);
        }

        $violationType->delete();

        return response()->json([
            'message' => 'تم حذف لائحة المخالفة بنجاح.',
        ]);
    }
}