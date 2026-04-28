<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Loan;
use App\Modules\HR\Models\LoanInstallment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class LoanService
{
    /**
     * اعتماد السلفة وتوليد جدول الأقساط آلياً
     */
    public function approveLoan(Loan $loan, int $approverId): Loan
    {
        if ($loan->status !== 'pending') {
            throw new Exception("لا يمكن اعتماد هذه السلفة لأن حالتها الحالية: {$loan->status}");
        }

        return DB::transaction(function () use ($loan, $approverId) {
            // 1. تحديث حالة السلفة
            $loan->update([
                'status' => 'approved',
                'approved_by' => $approverId
            ]);

            // 2. حساب قيمة القسط الشهري
            $installmentsCount = $loan->installments_count;
            $baseInstallmentAmount = round($loan->amount / $installmentsCount, 2);

            // معالجة الفروقات العشرية في القسط الأخير لضمان تطابق الإجمالي
            $totalCalculated = $baseInstallmentAmount * ($installmentsCount - 1);
            $lastInstallmentAmount = $loan->amount - $totalCalculated;

            $startDate = Carbon::parse($loan->deduction_start_date);

            // 3. توليد الأقساط
            for ($i = 0; $i < $installmentsCount; $i++) {
                $amount = ($i === $installmentsCount - 1) ? $lastInstallmentAmount : $baseInstallmentAmount;

                LoanInstallment::create([
                    'loan_id' => $loan->id,
                    'amount' => $amount,
                    // 🌟 التحديث هنا: استخدام addMonthsNoOverflow لحماية الأقساط من تخطي الأشهر القصيرة مثل فبراير
                    'due_month' => $startDate->copy()->addMonthsNoOverflow($i)->format('Y-m-d'),
                    'status' => 'pending',
                ]);
            }

            return $loan;
        });
    }

    /**
     * ربط السلفة بسند الصرف المحاسبي (تأكيد تسليم المبلغ للموظف)
     */
    public function markAsPaid(Loan $loan, int $voucherId): Loan
    {
        if ($loan->status !== 'approved') {
            throw new Exception("يجب اعتماد السلفة إدارياً قبل صرفها.");
        }

        $loan->update([
            'status' => 'paid_to_employee',
            'voucher_id' => $voucherId // ربطها مع الوحدة المحاسبية
        ]);

        return $loan;
    }
}
