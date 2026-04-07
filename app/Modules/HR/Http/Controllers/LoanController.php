<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Loan;
use App\Modules\HR\Http\Requests\Loan\StoreLoanRequest;
use App\Modules\HR\Http\Requests\Loan\UpdateLoanRequest;
use App\Modules\HR\Http\Resources\LoanResource;
use App\Modules\HR\Services\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    /**
     * حقن الخدمة وتفعيل السياسة الموحدة
     */
    public function __construct(private readonly LoanService $loanService)
    {
        /**
         * تفعيل السياسة (LoanPolicy)
         * - يربط العمليات الأساسية (index, store, show, update, destroy) آلياً
         * - تأكد أن مسمى المتغير في الراوت هو 'loan'
         */
        $this->authorizeResource(Loan::class, 'loan');
    }

    /**
     * عرض قائمة السلف
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // تم الفحص تلقائياً عبر LoanPolicy@viewAny

        $user = Auth::user();
        $query = Loan::with(['employee', 'approver']);

        // منطق الخدمة الذاتية (ESS)
        // إذا لم يكن لديه صلاحية العرض العام، يرى سلفه الشخصية فقط
        if (!$user->can('hr.loans.view') && !$user->can('hr.loans.manage') && $user->employee_id) {
            $query->where('employee_id', $user->employee_id);
        }

        // إمكانية الفلترة حسب الحالة أو الموظف
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return LoanResource::collection($query->latest()->paginate(15));
    }

    /**
     * إنشاء طلب سلفة جديد
     */
    public function store(StoreLoanRequest $request): LoanResource
    {
        // تم الفحص تلقائياً عبر LoanPolicy@create

        $data = $request->validated();
        $user = Auth::user();

        // حماية أمنية لمنع التلاعب بالهوية في الخدمة الذاتية
        if (!$user->can('hr.loans.manage')) {
            $data['employee_id'] = $user->employee_id;
        }

        $loan = Loan::create($data);

        return new LoanResource($loan->load('employee'));
    }

    /**
     * عرض سلفة محددة مع جدول الأقساط
     */
    public function show(Loan $loan): LoanResource
    {
        // تم الفحص تلقائياً عبر LoanPolicy@view
        return new LoanResource($loan->load(['employee', 'approver', 'installments']));
    }

    /**
     * تعديل بيانات السلفة (مسموح فقط والطلب معلق)
     */
    public function update(UpdateLoanRequest $request, Loan $loan): LoanResource
    {
        // تم الفحص تلقائياً عبر LoanPolicy@update
        $loan->update($request->validated());

        return new LoanResource($loan->load('employee'));
    }

    /**
     * إلغاء السلفة
     */
    public function destroy(Loan $loan): JsonResponse
    {
        // تم الفحص تلقائياً عبر LoanPolicy@delete
        $loan->delete();

        return response()->json(['message' => 'تم إلغاء طلب السلفة بنجاح.'], 200);
    }

    /**
     * [عملية إدارية]: اعتماد السلفة وتوليد الأقساط
     */
    public function approve(Loan $loan): JsonResponse
    {
        // دالة مخصصة، نحميها يدوياً باستخدام السياسة
        $this->authorize('approve', $loan);

        try {
            $this->loanService->approveLoan($loan, Auth::id());

            return response()->json([
                'message' => 'تم اعتماد السلفة وتوليد جدول الأقساط بنجاح.',
                'data' => new LoanResource($loan->fresh(['approver', 'installments']))
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * [عملية مالية]: تأكيد صرف السلفة للموظف
     */
    public function markAsPaid(Request $request, Loan $loan): JsonResponse
    {
        // نستخدم صلاحية الاعتماد أو الصرف المالي
        $this->authorize('approve', $loan);

        $request->validate([
            'voucher_id' => ['required', 'integer']
        ]);

        try {
            $this->loanService->markAsPaid($loan, (int) $request->voucher_id);

            return response()->json([
                'message' => 'تم تأكيد صرف السلفة وربطها بالسند المحاسبي.',
                'data' => new LoanResource($loan->fresh())
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
