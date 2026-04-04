<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Services\TreasuryService;
use App\Modules\Accounting\Http\Requests\StoreBankAccountRequest;
use App\Modules\Accounting\Http\Requests\UpdateBankAccountRequest;
use App\Modules\Accounting\Http\Resources\BankAccountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    // حقن السيرفس لإدارة العمليات المعقدة
    public function __construct(protected TreasuryService $treasuryService)
    {
        // تفعيل سياسات الحماية (BankAccountPolicy)
        // لاحظ أن اسم المتغير في الراوت يجب أن يكون bank_account
        $this->authorizeResource(BankAccount::class, 'bank_account');
    }



public function index(Request $request): JsonResponse
{
    $query = BankAccount::with(['account', 'currency']);

    // 1. فلترة البحث (اسم البنك، اسم الحساب، أو رقم الحساب)
    $query->when($request->search, function ($q, $search) {
        $q->where(function ($sub) use ($search) {
            $sub->where('bank_name', 'like', "%{$search}%")
                ->orWhere('account_name', 'like', "%{$search}%")
                ->orWhere('account_number', 'like', "%{$search}%");
        });
    });

    // 2. فلترة الحالة (نشط / غير نشط)
    // نستخدم filled لأن القيمة قد تكون '0' وهي قيمة false في php
    $query->when($request->filled('status'), function ($q) use ($request) {
        $q->where('is_active', $request->status);
    });

    $accounts = $query->latest()->get();

    return response()->json([
        'data' => BankAccountResource::collection($accounts)
    ]);
}


    /**
     * إضافة حساب بنكي جديد
     */
    public function store(StoreBankAccountRequest $request): JsonResponse
    {
        // السيرفس تقوم بإنشاء الحساب المالي (الأصول) + سجل البنك
        $bankAccount = $this->treasuryService->createBankAccount($request->validated());

        $bankAccount->load(['account', 'currency']);

        return response()->json([
            'message' => 'تم إضافة الحساب البنكي وإنشاء حسابه المالي بنجاح',
            'data'    => new BankAccountResource($bankAccount)
        ], 201);
    }

    /**
     * عرض تفاصيل حساب بنكي
     */
    public function show(BankAccount $bankAccount): JsonResponse
    {
        $bankAccount->load(['account', 'currency']);

        return response()->json([
            'data' => new BankAccountResource($bankAccount)
        ]);
    }

    /**
     * تحديث بيانات البنك
     */
    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount): JsonResponse
    {
        // السيرفس تحدث بيانات البنك وتعدل اسم الحساب المالي ليطابقه
        $updatedAccount = $this->treasuryService->updateBankAccount($bankAccount, $request->validated());

        return response()->json([
            'message' => 'تم تحديث بيانات الحساب البنكي بنجاح',
            'data'    => new BankAccountResource($updatedAccount)
        ]);
    }

    /**
     * حذف الحساب البنكي
     */
    public function destroy(BankAccount $bankAccount): JsonResponse
    {
        // السيرفس تحاول حذف البنك وحسابه المالي (إذا لم توجد قيود)
        $this->treasuryService->deleteBankAccount($bankAccount);

        return response()->json([
            'message' => 'تم حذف الحساب البنكي وحسابه المالي بنجاح'
        ]);
    }
}
