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
     * يقبل الفلترة: ?type=payment&status=posted&date_from=...&date_to=...&per_page=...
     */
    public function index(Request $request)
    {
        $query = Voucher::query()
            ->with(['branch', 'currency', 'box', 'bankAccount', 'details.account'])
            ->latest('date');

        // فلترة نوع السند (صرف / قبض)
        $query->when($request->filled('type'), function ($q) use ($request) {
            $q->where('type', $request->type);
        });

        // فلترة حالة السند (مسودة / معتمد / مرحل)
        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('status', $request->status);
        });

        // فلترة الفرع
        $query->when($request->filled('branch_id'), function ($q) use ($request) {
            $q->where('branch_id', $request->branch_id);
        });

        // فلترة من تاريخ
        $query->when($request->filled('date_from'), function ($q) use ($request) {
            $q->whereDate('date', '>=', $request->date_from);
        });

        // فلترة إلى تاريخ
        $query->when($request->filled('date_to'), function ($q) use ($request) {
            $q->whereDate('date', '<=', $request->date_to);
        });

        // البحث النصي في رقم السند، الرقم الورقي، رقم الشيك/العملية، اسم المستفيد، أو البيان
        $query->when($request->filled('search'), function ($q) use ($request) {
            $searchTerm = '%' . $request->search . '%';
            $q->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('number', 'like', $searchTerm)
                    ->orWhere('paper_ref', 'like', $searchTerm)
                    ->orWhere('bank_ref_number', 'like', $searchTerm)
                    ->orWhere('payee_name', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm);
            });
        });

        // دعم عدد العناصر لكل صفحة بشكل ديناميكي (افتراضي 20، وحد أقصى 2000 للطباعة والتقارير)
        $perPage = $request->integer('per_page', 20);
        if ($perPage <= 0 || $perPage > 2000) {
            $perPage = 20;
        }

        return VoucherResource::collection($query->paginate($perPage));
    }

    /**
     * عرض سند واحد بالتفصيل
     */
    public function show(Voucher $voucher)
    {
        $voucher->load([
            'details.account',
            'details.costCenter',
            'branch',
            'currency',
            'box',
            'bankAccount',
            'creator'
        ]);

        return new VoucherResource($voucher);
    }

    /**
     * إنشاء سند جديد
     */
    public function store(StoreVoucherRequest $request)
    {
        // التحقق من الصلاحية حسب النوع (لأن authorizeResource عامة)
        $permission = $request->type . '.create';
        if (! $request->user()->can($permission)) {
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
     * ترحيل السند (Post)
     * يحوله إلى قيد محاسبي ويمنع التعديل عليه
     */
    public function post(Voucher $voucher)
    {
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
     * إلغاء ترحيل السند (Unpost)
     * يعيد السند إلى مسودة ويحذف القيد المحاسبي المرتبط
     */
    public function unpost(Voucher $voucher): JsonResponse
    {
        $this->authorize('unpost', $voucher);

        try {
            $unpostedVoucher = $this->voucherService->unpostVoucher($voucher);

            return response()->json([
                'message' => 'تم إلغاء ترحيل السند بنجاح وإعادته إلى مسودة.',
                'data' => new VoucherResource($unpostedVoucher)
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * اعتماد السند (Approve) قبل الترحيل
     */
    public function approve(Voucher $voucher)
    {
        $this->authorize('approve', $voucher);

        if ($voucher->status !== VoucherStatus::Draft) {
            return response()->json(['message' => 'السند ليس في حالة مسودة.'], 400);
        }

        $voucher->update(['status' => VoucherStatus::Approved]);

        return response()->json(['message' => 'تم اعتماد السند بنجاح.']);
    }
}