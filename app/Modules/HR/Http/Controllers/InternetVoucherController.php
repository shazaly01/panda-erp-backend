<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\InternetVoucher;
use App\Modules\HR\Services\InternetVoucherService;
use App\Modules\HR\Http\Requests\InternetVoucher\ImportInternetVoucherRequest;
use App\Modules\HR\Http\Requests\InternetVoucher\AssignInternetVoucherRequest;
use App\Modules\HR\Http\Resources\InternetVoucherResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InternetVoucherController extends Controller
{
    protected InternetVoucherService $voucherService;

    public function __construct(InternetVoucherService $voucherService)
    {
        $this->voucherService = $voucherService;
    }

    /**
     * عرض الأكواد في الشاشة الأمامية (مع الفلترة والتقسيم)
     */
    public function index(Request $request)
    {
        // التحقق من صلاحية العرض
        // $this->authorize('viewAny', InternetVoucher::class);

        $query = InternetVoucher::with('employee')->latest();

        // فلترة حسب الحالة (متاح / مصروف)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // بحث برقم الكود
        if ($request->filled('search')) {
            $query->where('code', 'like', '%' . $request->search . '%');
        }

        $vouchers = $query->paginate($request->input('per_page', 15));

        return InternetVoucherResource::collection($vouchers);
    }

    /**
     * استيراد ملف الإكسيل/CSV
     */
    public function import(ImportInternetVoucherRequest $request): JsonResponse
    {
        try {
            // استقبال الخيارات الإضافية من الواجهة (مع قيم افتراضية)
            $hasHeader = filter_var($request->input('has_header', true), FILTER_VALIDATE_BOOLEAN);
            $capacity = $request->input('capacity', '1GB');

            $result = $this->voucherService->importFromCsv($request->file('file'), $hasHeader, $capacity);

            return response()->json([
                'message' => "تم استيراد {$result['imported_count']} كود بنجاح.",
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * صرف كود يدوياً لموظف من شاشة الإدارة
     */
    public function assignManually(AssignInternetVoucherRequest $request): JsonResponse
    {
        try {
            $voucher = $this->voucherService->assignVoucherManually($request->employee_id);

            return response()->json([
                'message' => 'تم صرف الكود بنجاح.',
                'data' => new InternetVoucherResource($voucher->load('employee'))
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
