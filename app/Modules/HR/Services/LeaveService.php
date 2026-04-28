<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\LeaveRequest;
use App\Modules\HR\Models\LeaveBalance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class LeaveService
{
    /**
     * اعتماد طلب الإجازة وخصم الرصيد
     */
    public function approveLeaveRequest(LeaveRequest $leaveRequest, int $approverId): LeaveRequest
    {
        if ($leaveRequest->status !== 'pending') {
            throw new Exception("لا يمكن اعتماد هذا الطلب لأن حالته الحالية: {$leaveRequest->status}");
        }

        return DB::transaction(function () use ($leaveRequest, $approverId) {
            $year = Carbon::parse($leaveRequest->start_date)->year;

            // 1. البحث عن رصيد الموظف لهذه السنة وهذا النوع من الإجازات
            $balanceRecord = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)
                ->where('year', $year)
                ->lockForUpdate() // قفل السجل برمجياً لمنع التعديل المزدوج في نفس اللحظة
                ->first();

            if (!$balanceRecord) {
                throw new Exception("الموظف ليس لديه رصيد مسجل لهذا النوع من الإجازات في سنة {$year}.");
            }

            // 2. التحقق من كفاية الرصيد
            if ($balanceRecord->balance < $leaveRequest->total_days) {
                throw new Exception("رصيد الإجازات غير كافٍ. الرصيد المتاح: {$balanceRecord->balance} يوم.");
            }

            // 3. خصم الرصيد
            $balanceRecord->used_days += $leaveRequest->total_days;
            $balanceRecord->balance -= $leaveRequest->total_days;
            $balanceRecord->save();

            // 4. تحديث حالة الطلب
            $leaveRequest->update([
                'status' => 'approved',
                'approved_by' => $approverId
            ]);

            return $leaveRequest;
        });
    }

    /**
     * رفض طلب الإجازة (يجب أن يكون معلقاً فقط)
     */
    public function rejectLeaveRequest(LeaveRequest $leaveRequest, int $approverId): LeaveRequest
    {
        // 🌟 حماية إضافية: لا يمكن رفض طلب إلا إذا كان في حالة انتظار
        if ($leaveRequest->status !== 'pending') {
            throw new Exception("لا يمكن رفض هذا الطلب لأنه ليس في حالة انتظار.");
        }

        $leaveRequest->update([
            'status' => 'rejected',
            'approved_by' => $approverId
        ]);

        return $leaveRequest;
    }

    /**
     * 🌟 إضافة جديدة: إلغاء إجازة معتمدة واسترجاع الرصيد (Refund)
     */
    public function cancelLeaveRequest(LeaveRequest $leaveRequest, int $cancelledById): LeaveRequest
    {
        if ($leaveRequest->status !== 'approved') {
            throw new Exception("لا يمكن إلغاء هذا الطلب لأنه ليس معتمداً.");
        }

        return DB::transaction(function () use ($leaveRequest, $cancelledById) {
            $year = Carbon::parse($leaveRequest->start_date)->year;

            // 1. جلب الرصيد لعمل الاسترجاع
            $balanceRecord = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($balanceRecord) {
                // 2. استرجاع الرصيد للموظف (Refund)
                $balanceRecord->used_days -= $leaveRequest->total_days;
                $balanceRecord->balance += $leaveRequest->total_days;
                $balanceRecord->save();
            }

            // 3. تحديث حالة الطلب
            $leaveRequest->update([
                'status' => 'cancelled',
                // يمكن إضافة حقل 'cancelled_by' إذا كان متوفراً في الموديل
            ]);

            return $leaveRequest;
        });
    }
}
