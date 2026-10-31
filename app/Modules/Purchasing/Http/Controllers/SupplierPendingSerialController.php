<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\StockSerial;
use App\Modules\Purchasing\Http\Resources\SupplierPendingSerialResource;
use App\Modules\Purchasing\Models\PurchaseReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierPendingSerialController extends Controller
{
    /**
     * استرجاع قائمة الأرقام التسلسلية (الأسطوانات) العالقة بحالة عهدة لدى المورد
     */
    public function __invoke(Request $request, ?int $supplierId = null): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseReceipt::class);

        $query = StockSerial::query()
            ->where('status', 'with_vendor')
            ->with(['product:id,name,sku']);

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->input('product_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where('serial_number', 'like', "%{$search}%");
        }

        $serials = $query->orderBy('serial_number')->get();

        return SupplierPendingSerialResource::collection($serials);
    }
}