<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\EmployeeShift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class ShiftService
{
    /**
     * تعيين موظف على وردية جديدة (وإغلاق الوردية السابقة آلياً إذا لزم الأمر)
     */
    public function assignEmployeeToShift(array $data): EmployeeShift
    {
        return DB::transaction(function () use ($data) {
            $startDate = Carbon::parse($data['start_date']);

            // البحث عن الوردية الحالية المفتوحة (التي ليس لها تاريخ نهاية، أو تاريخ نهايتها في المستقبل)
            $currentActiveShift = EmployeeShift::where('employee_id', $data['employee_id'])
                ->where(function ($query) use ($startDate) {
                    $query->whereNull('end_date')
                          ->orWhere('end_date', '>=', $startDate);
                })
                ->first();

            // إذا وجدنا وردية مفتوحة تتداخل مع الوردية الجديدة
            if ($currentActiveShift) {
                // نغلق الوردية القديمة في اليوم الذي يسبق بداية الوردية الجديدة
                $currentActiveShift->update([
                    'end_date' => $startDate->copy()->subDay()->format('Y-m-d')
                ]);
            }

            // إنشاء التعيين الجديد
            return EmployeeShift::create($data);
        });
    }
}
