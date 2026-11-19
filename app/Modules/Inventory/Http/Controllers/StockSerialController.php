<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Enums\StockSerialStatus;
use App\Modules\Inventory\Http\Requests\Serials\DispatchSerialsToVendorRequest;
use App\Modules\Inventory\Http\Requests\Serials\ReturnEmptySerialsRequest;
use App\Modules\Inventory\Http\Requests\Serials\UpdateStockSerialRequest;
use App\Modules\Inventory\Http\Resources\StockSerialResource;
use App\Modules\Inventory\Models\StockSerial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class StockSerialController extends Controller
{
    /**
     * عرض قائمة الأرقام التسلسلية مع الفلترة والبحث ودعم القوائم المنسدلة
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockSerial::class);

        $query = StockSerial::query()
            ->with([
                'product',
                'warehouse',
                'location',
                'batch',
            ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where('serial_number', 'like', "%{$search}%");
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->input('product_id'));
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', (int) $request->input('warehouse_id'));
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if (is_array($status)) {
                $query->whereIn('status', $status);
            } else {
                $query->where('status', $status);
            }
        }

        $query->orderByDesc('id');

        // دعم جلب كامل السجلات للـ Dropdowns في شاشات الاستبدال والصرف
        if ($request->boolean('all') || (int) $request->input('per_page') === -1) {
            $serials = $query->get();
            return StockSerialResource::collection($serials);
        }

        $perPage = (int) $request->input('per_page', 15);
        $serials = $query->paginate($perPage);

        return StockSerialResource::collection($serials);
    }

    /**
     * عرض تفاصيل أسطوانة أو رقم تسلسلي محدد
     */
    public function show(StockSerial $serial): StockSerialResource
    {
        $this->authorize('view', $serial);

        $serial->loadMissing([
            'product',
            'warehouse',
            'location',
            'batch',
        ]);

        return new StockSerialResource($serial);
    }

    /**
     * تعديل بيانات الأسطوانة وتصحيح الأخطاء التشغيلية أو التراجع عن التسليم الخاطئ
     */
    public function update(UpdateStockSerialRequest $request, StockSerial $serial): JsonResponse
    {
        $this->authorize('update', $serial);

        $validated = $request->validated();

        return DB::transaction(function () use ($serial, $validated): JsonResponse {
            // 1. معالجة التراجع عن الإرسال الخاطئ للمورد (Undo accidental dispatch)
            if (! empty($validated['revert_to_empty'])) {
                if ($serial->status !== StockSerialStatus::WITH_VENDOR) {
                    return response()->json([
                        'message' => "لا يمكن التراجع عن حالة الأسطوانة إلا إذا كانت حالتها حالياً لدى المورد (الحالة الحالية: {$serial->status->label()}).",
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $serial->markAsEmpty(
                    warehouseId: (int) $validated['warehouse_id'],
                    locationId: isset($validated['location_id']) ? (int) $validated['location_id'] : null,
                    notes: $validated['notes'] ?? 'تراجع وتصحيح: إعادة الأسطوانة كفارغة بالمستودع إثر تصحيح خطأ الإرسال'
                );
            }

            // 2. تحديث الحقول المعدلة (رقم السيريال، الوزن الفارغ، الموقع، الملاحظات)
            $updatePayload = [];

            if (array_key_exists('serial_number', $validated)) {
                $updatePayload['serial_number'] = trim((string) $validated['serial_number']);
            }

            if (array_key_exists('tare_weight', $validated)) {
                $newTare = $validated['tare_weight'] !== null ? (float) $validated['tare_weight'] : null;
                $updatePayload['tare_weight'] = $newTare;

                // تحديث الوزن الإجمالي وصافي الغاز بالتناسق مع الوزن الفارغ الجديد
                if ($serial->status === StockSerialStatus::EMPTY) {
                    $updatePayload['gross_weight'] = $newTare;
                    $updatePayload['current_gas_weight'] = 0.0000;
                } elseif ($serial->status === StockSerialStatus::IN_STOCK && $serial->gross_weight !== null && $newTare !== null) {
                    $updatePayload['current_gas_weight'] = max(0.0, round((float) $serial->gross_weight - $newTare, 4));
                }
            }

            if (array_key_exists('location_id', $validated) && empty($validated['revert_to_empty'])) {
                $updatePayload['location_id'] = $validated['location_id'] ? (int) $validated['location_id'] : null;
            }

            if (array_key_exists('notes', $validated) && empty($validated['revert_to_empty'])) {
                $updatePayload['notes'] = $validated['notes'];
            }

            if (! empty($updatePayload)) {
                $serial->update($updatePayload);
            }

            $serial->loadMissing(['product', 'warehouse', 'location', 'batch']);

            return response()->json([
                'message' => 'تم تحديث بيانات الأسطوانة بنجاح.',
                'data'    => new StockSerialResource($serial),
            ], Response::HTTP_OK);
        });
    }

    /**
     * إثبات فراغ الأسطوانة بالمحطة وتحويل حالتها إلى فارغة بالمستودع
     */
    public function markAsEmpty(Request $request, StockSerial $serial): JsonResponse
    {
        $this->authorize('update', $serial);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:inventory_warehouses,id'],
            'location_id'  => ['nullable', 'integer', 'exists:inventory_warehouse_locations,id'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ], [
            'warehouse_id.required' => 'يجب تحديد المستودع الذي ستُعاد إليه الأسطوانة الفارغة.',
            'warehouse_id.exists'   => 'المستودع المحدد غير موجود.',
            'location_id.exists'    => 'الموقع / الرف المحدد غير موجود.',
        ]);

        if ($serial->status !== StockSerialStatus::IN_SERVICE) {
            return response()->json([
                'message' => "لا يمكن إثبات فراغ الأسطوانة إلا إذا كانت قيد التشغيل بالمحطة (الحالة الحالية: {$serial->status->label()}).",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $serial->markAsEmpty(
            warehouseId: (int) $validated['warehouse_id'],
            locationId: isset($validated['location_id']) ? (int) $validated['location_id'] : null,
            notes: $validated['notes'] ?? 'تم إثبات اكتمال استهلاك الغاز وتحويل الأسطوانة لفارغة بالمستودع'
        );

        $serial->loadMissing(['product', 'warehouse', 'location', 'batch']);

        return response()->json([
            'message' => 'تم تحويل حالة الأسطوانة إلى فارغة بنجاح.',
            'data'    => new StockSerialResource($serial),
        ], Response::HTTP_OK);
    }

    /**
     * إرجاع مجموعة أسطوانات فارغة من مخزن التشغيل بالمحطة إلى المخزن الرئيسي
     */
    public function returnToMainWarehouse(ReturnEmptySerialsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $targetWarehouseId = (int) $validated['warehouse_id'];
        $serialIds = array_map('intval', $validated['serial_ids']);
        $notes = $validated['notes'] ?? 'إرجاع أسطوانات فارغة إلى المستودع الرئيسي';

        return DB::transaction(function () use ($serialIds, $targetWarehouseId, $notes): JsonResponse {
            $serials = StockSerial::query()
                ->whereIn('id', $serialIds)
                ->lockForUpdate()
                ->get();

            foreach ($serials as $serial) {
                if ($serial->status !== StockSerialStatus::EMPTY) {
                    return response()->json([
                        'message' => "الأسطوانة ({$serial->serial_number}) ليست بحالة فارغة (حالتها الحالية: {$serial->status->label()})، لا يمكن إرجاعها للمخزن الرئيسي كفارغة.",
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            foreach ($serials as $serial) {
                $serial->markAsEmpty(
                    warehouseId: $targetWarehouseId,
                    locationId: null,
                    notes: $notes
                );
            }

            $serials->loadMissing(['product', 'warehouse', 'location', 'batch']);

            return response()->json([
                'message' => 'تم إرجاع الأسطوانات الفارغة إلى المستودع الرئيسي بنجاح.',
                'count'   => $serials->count(),
                'data'    => StockSerialResource::collection($serials),
            ], Response::HTTP_OK);
        });
    }

    /**
     * تسليم الأسطوانات الفارغة من المخزن الرئيسي إلى المورد / مصنع التعبئة
     */
    public function dispatchToVendor(DispatchSerialsToVendorRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $serialIds = array_map('intval', $validated['serial_ids']);
        $vendorName = $validated['vendor_name'] ?? null;
        $customNotes = $validated['notes'] ?? null;

        $vendorNote = trim(($vendorName ? "تسليم للمورد: {$vendorName}. " : '') . ($customNotes ?? 'تسليم الأسطوانة للتعبئة لدى المورد'));

        return DB::transaction(function () use ($serialIds, $vendorNote): JsonResponse {
            $serials = StockSerial::query()
                ->with('warehouse')
                ->whereIn('id', $serialIds)
                ->lockForUpdate()
                ->get();

            foreach ($serials as $serial) {
                if ($serial->status !== StockSerialStatus::EMPTY) {
                    return response()->json([
                        'message' => "الأسطوانة ({$serial->serial_number}) ليست بحالة فارغة (حالتها: {$serial->status->label()})، ولا يمكن تسليمها للمورد للتعبئة.",
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                if ($serial->warehouse && (bool) $serial->warehouse->is_operating) {
                    return response()->json([
                        'message' => "الأسطوانة ({$serial->serial_number}) لا تزال في مخزن التشغيل بالمحطة؛ يجب إرجاعها إلى المخزن الرئيسي أولاً قبل تسليمها للمورد.",
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                if ($serial->warehouse_id === null) {
                    return response()->json([
                        'message' => "الأسطوانة ({$serial->serial_number}) غير مقيدة بمستودع حالياً.",
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            foreach ($serials as $serial) {
                $serial->markAsWithVendor($vendorNote);
            }

            $serials->loadMissing(['product', 'warehouse', 'location', 'batch']);

            return response()->json([
                'message' => 'تم تسليم الأسطوانات للمورد للتعبئة بنجاح، وتحويل حالتها إلى (لدى المورد).',
                'count'   => $serials->count(),
                'data'    => StockSerialResource::collection($serials),
            ], Response::HTTP_OK);
        });
    }
}