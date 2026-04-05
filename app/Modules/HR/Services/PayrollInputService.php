<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\PayrollInput;
use App\Modules\HR\Models\Employee;
use Exception;

class PayrollInputService
{
    /**
     * إضافة حركة مالية متغيرة (حافز، خصم، إلخ) على الموظف
     */
    public function addInput(Employee $employee, string $type, float $amount, string $date, ?string $reason, int $createdById): PayrollInput
    {
        // التحقق من نوع الحركة
        $validTypes = ['bonus', 'penalty', 'allowance', 'deduction'];
        if (!in_array($type, $validTypes)) {
            throw new Exception("نوع الحركة غير صالح. الأنواع المسموحة: " . implode(', ', $validTypes));
        }

        if ($amount <= 0) {
            throw new Exception("يجب أن يكون المبلغ أكبر من صفر.");
        }

        // تسجيل الحركة
        return PayrollInput::create([
            'employee_id' => $employee->id,
            'type' => $type,
            'amount' => $amount,
            'date' => $date,
            'reason' => $reason,
            'is_processed' => false, // لم تدخل في مسير الرواتب بعد
            'created_by' => $createdById,
        ]);
    }
}
