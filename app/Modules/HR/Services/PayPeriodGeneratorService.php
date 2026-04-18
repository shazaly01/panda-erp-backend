<?php

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\PayGroup;
use App\Modules\HR\Models\PayPeriod;
use Carbon\Carbon;
use Exception;

class PayPeriodGeneratorService
{
    /**
     * توليد الفترات المالية لمجموعة دفع معينة في سنة محددة
     */
    public function generate(PayGroup $payGroup, int $year): array
    {
        // التحقق مما إذا كانت الفترات مولدة مسبقاً لهذه المجموعة في هذه السنة لمنع التكرار
        $existingPeriods = PayPeriod::where('pay_group_id', $payGroup->id)
            ->whereYear('start_date', $year)
            ->exists();

        if ($existingPeriods) {
            throw new Exception("الفترات المالية لسنة {$year} تم توليدها مسبقاً لهذه المجموعة.");
        }

        $periods = [];
        $frequency = $payGroup->frequency?->value ?? $payGroup->frequency;

        switch ($frequency) {
            case 'monthly':
                $periods = $this->generateMonthlyPeriods($payGroup->id, $year);
                break;
            case 'weekly':
                $periods = $this->generateWeeklyPeriods($payGroup->id, $year);
                break;
            case 'bi_weekly':
                $periods = $this->generateBiWeeklyPeriods($payGroup->id, $year);
                break;
            default:
                throw new Exception("دورة الراتب غير مدعومة للتوليد التلقائي بعد.");
        }

        // إدخال البيانات دفعة واحدة (Bulk Insert) لتحسين الأداء
        PayPeriod::insert($periods);

        return $periods;
    }

    private function generateMonthlyPeriods(int $groupId, int $year): array
    {
        $periods = [];
        for ($month = 1; $month <= 12; $month++) {
            $start = Carbon::create($year, $month, 1);
            $end = $start->copy()->endOfMonth();

            $periods[] = [
                'pay_group_id' => $groupId,
                'name'         => "شهر " . $start->translatedFormat('F Y'), // مثال: شهر أبريل 2026
                'start_date'   => $start->format('Y-m-d'),
                'end_date'     => $end->format('Y-m-d'),
                'status'       => 'pending', // حالة مبدئية: مجدولة/قيد الانتظار
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }
        return $periods;
    }

    private function generateWeeklyPeriods(int $groupId, int $year): array
    {
        $periods = [];
        // نبدأ من أول يوم في السنة، ثم نعود لأول يوم أحد (أو السبت حسب إعدادات الأسبوع)
        $start = Carbon::create($year, 1, 1)->startOfWeek(Carbon::SUNDAY);
        $weekNumber = 1;

        while ($start->year <= $year || ($start->year == $year + 1 && $start->month == 1 && $start->day <= 6)) {
            $end = $start->copy()->addDays(6);

            // التأكد من أن الأسبوع يقع أغلبه في السنة المستهدفة لتجنب التداخل الكبير
            if ($start->year == $year || $end->year == $year) {
                $periods[] = [
                    'pay_group_id' => $groupId,
                    'name'         => "الأسبوع {$weekNumber} - {$year}",
                    'start_date'   => $start->format('Y-m-d'),
                    'end_date'     => $end->format('Y-m-d'),
                    'status'       => 'pending',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
                $weekNumber++;
            }
            $start->addWeek();
        }
        return $periods;
    }

    private function generateBiWeeklyPeriods(int $groupId, int $year): array
    {
        $periods = [];
        $start = Carbon::create($year, 1, 1)->startOfWeek(Carbon::SUNDAY);
        $periodNumber = 1;

        while ($start->year <= $year) {
            $end = $start->copy()->addDays(13); // 14 يوم

            if ($start->year == $year || $end->year == $year) {
                $periods[] = [
                    'pay_group_id' => $groupId,
                    'name'         => "فترة نصف شهرية {$periodNumber} - {$year}",
                    'start_date'   => $start->format('Y-m-d'),
                    'end_date'     => $end->format('Y-m-d'),
                    'status'       => 'pending',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
                $periodNumber++;
            }
            $start->addWeeks(2);
        }
        return $periods;
    }
}
