<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Loan;
use App\Modules\HR\Http\Requests\Loan\StoreLoanRequest;
use App\Modules\HR\Http\Requests\Loan\UpdateLoanRequest;
use App\Modules\HR\Http\Resources\LoanResource; // عدلنا المسار
use App\Modules\HR\Services\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function __construct(private readonly LoanService $loanService)
    {
    }

    /**
     * عرض قائمة السلف
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Loan::class);

        $user = Auth::user();
        $query = Loan::with(['employee', 'approver']);

        // فلترة بوابة الخدمة الذاتية (ESS)
        if (!$user->hasPermissionTo('hr.loans.view') && $user->employee_id) {
            $query->where('employee_id', $user->employee_id);
        }

        return LoanResource::collection($query->latest()->paginate(15));
    }

    /**
     * إنشاء طلب سلفة جديد
     */
    public function store(StoreLoanRequest $request): LoanResource
    {
        $this->authorize('create', Loan::class);

        $data = $request->validated();
        $user = Auth::user();

        // حماية أمنية: إجبار الطلب على رقم الموظف الحالي إذا لم يكن يملك صلاحية الإدارة
        if (!$user->hasPermissionTo('hr.loans.manage')) {
            $data['employee_id'] = $user->employee_id;
        }

        $loan = Loan::create($data);

        return new LoanResource($loan->load('employee'));
    }

    /**
     * عرض سلفة محددة (مع جدول أقساطها)
     */
    public function show(Loan $loan): LoanResource
    {
        $this->authorize('view', $loan);

        // هنا نستخدم Eager Loading لجلب جدول الأقساط مع السلفة ليعرضها الـ Frontend
        return new LoanResource($loan->load(['employee', 'approver', 'installments']));
    }

    /**
     * تعديل بيانات السلفة (مسموح فقط والطلب معلق)
     */
    public function update(UpdateLoanRequest $request, Loan $loan): LoanResource
    {
        $this->authorize('update', $loan);

        $loan->update($request->validated());

        return new LoanResource($loan->load('employee'));
    }

    /**
     * إلغاء السلفة
     */
    public function destroy(Loan $loan): JsonResponse
    {
        $this->authorize('delete', $loan);

        $loan->delete();

        return response()->json(['message' => 'تم إلغاء طلب السلفة بنجاح.'], 200);
    }

    /**
     * [عملية إدارية]: اعتماد السلفة وتوليد الأقساط
     */
    public function approve(Loan $loan): JsonResponse
    {
        $this->authorize('approve', $loan);

        try {
            // الـ Service ستقوم بتغيير الحالة وتقسيم المبلغ وإنشاء صفوف الأقساط آلياً
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
     * [عملية مالية]: تأكيد صرف السلفة للموظف من الخزينة/البنك
     */
    public function markAsPaid(Request $request, Loan $loan): JsonResponse
    {
        // نستخدم نفس صلاحية الاعتماد، أو يمكنك لاحقاً إنشاء صلاحية محاسبية خاصة
        $this->authorize('approve', $loan);

        // نطلب رقم السند المحاسبي للربط
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
