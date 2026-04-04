<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Modules\Accounting\Models\Voucher;
use App\Modules\Accounting\Services\VoucherService;
use App\Modules\Accounting\Http\Requests\StoreVoucherRequest;
use App\Modules\Accounting\Http\Requests\UpdateVoucherRequest;
use App\Modules\Accounting\Http\Resources\VoucherResource;
use App\Modules\Accounting\Enums\VoucherStatus;

class VoucherController extends Controller
{
    public function __construct(
        protected VoucherService $voucherService
    ) {
        // تفعيل البوليسي تلقائياً على كل الدوال (تعتمد على Route Model Binding)
        $this->authorizeResource(Voucher::class, 'voucher');
    }

    /**
     * عرض قائمة السندات
     * يقبل الفلترة: ?type=payment&status=posted&date_from=...
     */
  public function index(Request $request)
{
    $query = Voucher::query()
        ->with(['branch', 'currency', 'box', 'bankAccount'])
        ->latest('date');

    // استخدام filled للتأكد أن القيمة ليست فارغة
    $query->when($request->filled('type'), function($q) use ($request) {
        $q->where('type', $request->type);
    });

    $query->when($request->filled('status'), function($q) use ($request) {
        $q->where('status', $request->status);
    });

    $query->when($request->filled('branch_id'), function($q) use ($request) {
        $q->where('branch_id', $request->branch_id);
    });

    // إضافة الفلتر النصي للبحث في رقم السند، اسم المستفيد، أو البيان
    $query->when($request->filled('search'), function($q) use ($request) {
        $searchTerm = '%' . $request->search . '%';
        $q->where(function($subQuery) use ($searchTerm) {
            $subQuery->where('number', 'like', $searchTerm)
                     ->orWhere('payee_name', 'like', $searchTerm)
                     ->orWhere('description', 'like', $searchTerm);
        });
    });

    return VoucherResource::collection($query->paginate(20));
}

    /**
     * عرض سند واحد بالتفصيل
     */
   public function show(Voucher $voucher)
    {
        // أضف 'box' و 'bankAccount' إلى قائمة الـ load
        $voucher->load([
            'details.account',
            'details.costCenter',
            'branch',
            'currency',
            'box',         // <--- أضف هذا
            'bankAccount'  // <--- أضف هذا
        ]);

        return new VoucherResource($voucher);
    }

    /**
     * إنشاء سند جديد
     */
    public function store(StoreVoucherRequest $request)
    {
        // التحقق من الصلاحية حسب النوع (لأن authorizeResource عامة)
        // إذا كان الطلب 'payment'، نتأكد أن المستخدم لديه 'payment.create'
        $permission = $request->type . '.create';
        if (!$request->user()->can($permission)) {
            abort(403, 'لا تملك صلاحية إنشاء هذا النوع من السندات.');
        }

        try {
            $voucher = $this->voucherService->createVoucher($request->validated());

            return new VoucherResource($voucher);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * تحديث سند (مسودة)
     */
    public function update(UpdateVoucherRequest $request, Voucher $voucher)
    {
        try {
            $updatedVoucher = $this->voucherService->updateVoucher($voucher, $request->validated());

            return new VoucherResource($updatedVoucher);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * حذف سند (مسودة)
     */
    public function destroy(Voucher $voucher)
    {
        if ($voucher->status === VoucherStatus::Posted) {
            return response()->json(['message' => 'لا يمكن حذف سند مرحل.'], 400);
        }

        $voucher->delete();

        return response()->json(['message' => 'تم حذف السند بنجاح.']);
    }

    /**
     * [جديد] ترحيل السند (Post)
     * يحوله إلى قيد محاسبي ويمنع التعديل عليه
     */
    public function post(Voucher $voucher)
    {
        // التحقق من الصلاحية (عبر البوليسي)
        $this->authorize('post', $voucher);

        try {
            $postedVoucher = $this->voucherService->postVoucher($voucher);

            return response()->json([
                'message' => 'تم ترحيل السند وإنشاء القيد المحاسبي بنجاح.',
                'data' => new VoucherResource($postedVoucher)
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * [اضافي] اعتماد السند (Approve) قبل الترحيل
     * إذا كنت ستطبق دورة الموافقة
     */
    public function approve(Voucher $voucher)
    {
        $this->authorize('approve', $voucher);

        if ($voucher->status !== VoucherStatus::Draft) {
            return response()->json(['message' => 'السند ليس في حالة مسودة.'], 400);
        }

        $voucher->update(['status' => VoucherStatus::Approved]); // أو Pending حسب Enums لديك

        return response()->json(['message' => 'تم اعتماد السند بنجاح.']);
    }
}
