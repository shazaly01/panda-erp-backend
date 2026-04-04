<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Http\Requests\StoreCurrencyRequest;
use App\Modules\Accounting\Http\Requests\UpdateCurrencyRequest;
use App\Modules\Accounting\Http\Resources\CurrencyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurrencyController extends Controller
{
    public function __construct()
    {
        // تفعيل الحماية تلقائياً بناءً على CurrencyPolicy
        $this->authorizeResource(Currency::class, 'currency');
    }

    /**
     * عرض كل العملات
     */
    public function index(): JsonResponse
    {
        $currencies = Currency::orderBy('is_base', 'desc')->get(); // الأساسية تظهر أولاً

        return response()->json([
            'data' => CurrencyResource::collection($currencies)
        ]);
    }

    /**
     * إضافة عملة جديدة
     */
    public function store(StoreCurrencyRequest $request): JsonResponse
    {
        $currency = DB::transaction(function () use ($request) {
            $data = $request->validated();

            // إذا تم اختيارها كعملة أساسية، نجعل الباقي غير أساسي
            if (!empty($data['is_base']) && $data['is_base'] === true) {
                Currency::where('is_base', true)->update(['is_base' => false]);
            }

            return Currency::create($data);
        });

        return response()->json([
            'message' => 'تم إضافة العملة بنجاح',
            'data'    => new CurrencyResource($currency)
        ], 201);
    }

    /**
     * عرض عملة محددة
     */
    public function show(Currency $currency): JsonResponse
    {
        return response()->json([
            'data' => new CurrencyResource($currency)
        ]);
    }

    /**
     * تحديث العملة
     */
    public function update(UpdateCurrencyRequest $request, Currency $currency): JsonResponse
    {
        $currency = DB::transaction(function () use ($request, $currency) {
            $data = $request->validated();

            // منطق تبديل العملة الأساسية
            if (!empty($data['is_base']) && $data['is_base'] === true) {
                Currency::where('id', '!=', $currency->id)
                        ->where('is_base', true)
                        ->update(['is_base' => false]);
            }

            $currency->update($data);
            return $currency;
        });

        return response()->json([
            'message' => 'تم تحديث بيانات العملة بنجاح',
            'data'    => new CurrencyResource($currency)
        ]);
    }

    /**
     * حذف العملة
     */
    public function destroy(Currency $currency): JsonResponse
    {
        // التحقق قبل الحذف (اختياري، لأن قاعدة البيانات تمنع ذلك بفضل Foreign Keys)
        if ($currency->is_base) {
            return response()->json(['message' => 'لا يمكن حذف العملة الأساسية للنظام.'], 422);
        }

        $currency->delete();

        return response()->json([
            'message' => 'تم حذف العملة بنجاح'
        ]);
    }
}
