<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\PayPeriod;
use App\Modules\HR\Http\Resources\PayPeriodResource;
use App\Modules\HR\Http\Requests\PayPeriod\StorePayPeriodRequest;
use App\Modules\HR\Http\Requests\PayPeriod\UpdatePayPeriodRequest;
use App\Modules\HR\Http\Requests\PayPeriod\GeneratePayPeriodsRequest;
use App\Modules\HR\Services\PayPeriodGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayPeriodController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(PayPeriod::class, 'pay_period');
    }

public function index(Request $request) // أزلنا JsonResponse لتتوافق مع الـ Resource التلقائي
    {
        $query = PayPeriod::with('payGroup');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('pay_group_id')) {
            $query->where('pay_group_id', $request->pay_group_id);
        }

        // 🌟 التعديل هنا: استخدام paginate بدلاً من get
        $periods = $query->orderBy('start_date', 'desc')->paginate(15);

        // 🌟 إرجاع الـ Resource مباشرة ليقوم لارافيل بتغليف البيانات بـ data و meta تلقائياً
        return PayPeriodResource::collection($periods);
    }


    public function store(StorePayPeriodRequest $request): JsonResponse
    {
        $period = PayPeriod::create($request->validated());

        return response()->json([
            'message' => 'تم إنشاء الفترة المالية بنجاح',
            'data' => new PayPeriodResource($period->load('payGroup')),
        ], 201);
    }

    public function show(PayPeriod $payPeriod): JsonResponse
    {
        return response()->json(new PayPeriodResource($payPeriod->load('payGroup')));
    }

    public function update(UpdatePayPeriodRequest $request, PayPeriod $payPeriod): JsonResponse
    {
        $payPeriod->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث الفترة المالية بنجاح',
            'data' => new PayPeriodResource($payPeriod->load('payGroup')),
        ]);
    }

    public function destroy(PayPeriod $payPeriod): JsonResponse
    {
        if ($payPeriod->payrollBatches()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف الفترة لوجود مسيرات رواتب مرتبطة بها'], 422);
        }

        $payPeriod->delete();
        return response()->json(['message' => 'تم حذف الفترة المالية بنجاح']);
    }


    /**
     * توليد الفترات المالية تلقائياً (Bulk Generation)
     */
    public function generate(GeneratePayPeriodsRequest $request, PayPeriodGeneratorService $generatorService): JsonResponse
    {
        // $this->authorize('create', PayPeriod::class); // تأكد من التراخيص

        try {
            $payGroup = \App\Modules\HR\Models\PayGroup::findOrFail($request->pay_group_id);

            $periods = $generatorService->generate($payGroup, (int) $request->year);

            return response()->json([
                'message' => 'تم توليد الفترات المالية للسنة بنجاح.',
                'count' => count($periods)
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'تعذر توليد الفترات المالية.',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
