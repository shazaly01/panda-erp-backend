<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Enums\StockSerialStatus;
use App\Modules\Inventory\Http\Resources\StockSerialResource;
use App\Modules\Inventory\Models\StockSerial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
     * إثبات فراغ الأسطوانة بالمحطة وتحويل حالتها إلى فارغة بالمستودع
     */
    public function markAsEmpty(Request $request, StockSerial $serial): JsonResponse
    {
        $this->authorize('update', $serial);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:inventory_warehouses,id'],
            'location_id' => ['nullable', 'integer', 'exists:inventory_warehouse_locations,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'warehouse_id.required' => 'يجب تحديد المستودع الذي ستُعاد إليه الأسطوانة الفارغة.',
            'warehouse_id.exists' => 'المستودع المحدد غير موجود.',
            'location_id.exists' => 'الموقع / الرف المحدد غير موجود.',
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
            'data' => new StockSerialResource($serial),
        ], Response::HTTP_OK);
    }
}