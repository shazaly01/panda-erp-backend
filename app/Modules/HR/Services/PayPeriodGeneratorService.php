<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\PayGroup;
use App\Modules\HR\Models\PayPeriod;
use Carbon\Carbon;
use Exception;

class PayPeriodGeneratorService
{
    /**
     * أسماء الأشهر باللغة العربية لضمان دقة التسمية وتجنب خلط اللغات
     */
    private const ARABIC_MONTHS = [
        1  => 'يناير',
        2  => 'فبراير',
        3  => 'مارس',
        4  => 'أبريل',
        5  => 'مايو',
        6  => 'يونيو',
        7  => 'يوليو',
        8  => 'أغسطس',
        9  => 'سبتمبر',
        10 => 'أكتوبر',
        11 => 'نوفمبر',
        12 => 'ديسمبر',
    ];

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

    /**
     * توليد الفترات الشهرية (12 شهراً) بالترقيم واللغة العربية وحالة مفتوحة
     */
    private function generateMonthlyPeriods(int $groupId, int $year): array
    {
        $periods = [];
        $now = now();

        for ($month = 1; $month <= 12; $month++) {
            $start = Carbon::create($year, $month, 1);
            $end = $start->copy()->endOfMonth();

            $monthNumber = sprintf('%02d', $month);
            $monthArabicName = self::ARABIC_MONTHS[$month] ?? '';
            $periodName = "شهر {$monthNumber} ({$monthArabicName}) {$year}";

            $periods[] = [
                'pay_group_id' => $groupId,
                'name'         => $periodName,
                'start_date'   => $start->format('Y-m-d'),
                'end_date'     => $end->format('Y-m-d'),
                'status'       => 'open',
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        return $periods;
    }

    /**
     * توليد الفترات الأسبوعية
     */
    private function generateWeeklyPeriods(int $groupId, int $year): array
    {
        $periods = [];
        $now = now();

        $start = Carbon::create($year, 1, 1)->startOfWeek(Carbon::SUNDAY);
        $weekNumber = 1;

        while ($start->year <= $year || ($start->year == $year + 1 && $start->month == 1 && $start->day <= 6)) {
            $end = $start->copy()->addDays(6);

            if ($start->year == $year || $end->year == $year) {
                $weekFormatted = sprintf('%02d', $weekNumber);

                $periods[] = [
                    'pay_group_id' => $groupId,
                    'name'         => "الأسبوع {$weekFormatted} - {$year}",
                    'start_date'   => $start->format('Y-m-d'),
                    'end_date'     => $end->format('Y-m-d'),
                    'status'       => 'open',
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
                $weekNumber++;
            }
            $start->addWeek();
        }

        return $periods;
    }

    /**
     * توليد الفترات النصف شهرية (كل أسبوعين)
     */
    private function generateBiWeeklyPeriods(int $groupId, int $year): array
    {
        $periods = [];
        $now = now();

        $start = Carbon::create($year, 1, 1)->startOfWeek(Carbon::SUNDAY);
        $periodNumber = 1;

        while ($start->year <= $year) {
            $end = $start->copy()->addDays(13);

            if ($start->year == $year || $end->year == $year) {
                $periodFormatted = sprintf('%02d', $periodNumber);

                $periods[] = [
                    'pay_group_id' => $groupId,
                    'name'         => "فترة نصف شهرية {$periodFormatted} - {$year}",
                    'start_date'   => $start->format('Y-m-d'),
                    'end_date'     => $end->format('Y-m-d'),
                    'status'       => 'open',
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
                $periodNumber++;
            }
            $start->addWeeks(2);
        }

        return $periods;
    }
}