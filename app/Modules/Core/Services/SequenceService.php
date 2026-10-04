<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class SequenceService
{
    /**
     * توليد الرقم التالي بناءً على الصيغة وتاريخ المستند المحدد
     *
     * @param string $documentCode كود المستند (مثال: acc_payment, acc_receipt, acc_journal_entry)
     * @param int|null $branchId رقم الفرع (لفصل العدادات حسب الفرع إن لزم)
     * @param string|null $prefix بادئة الفرع (مثال: JD أو RY)
     * @param string|null $date تاريخ المستند المالي (إن لم يمرر يتم اعتماد تاريخ اليوم)
     * @return string الرقم النهائي المنسق
     */
    public function generateNumber(
        string $documentCode,
        ?int $branchId = null,
        ?string $prefix = null,
        ?string $date = null
    ): string {
        return DB::transaction(function () use ($documentCode, $branchId, $prefix, $date) {

            // 1. جلب إعدادات التسلسل مع قفل الصف لمنع تضارب العمليات المتزامنة
            $sequence = DB::table('sequences')
                ->where('model', $documentCode)
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                throw new Exception("لم يتم ضبط إعدادات التسلسل للمستند: {$documentCode}");
            }

            // اعتماد تاريخ المستند المالي الفعلي بدلاً من تاريخ اللحظة الحالية
            $targetDate = $date ? Carbon::parse($date) : Carbon::now();
            $nextValue = (int) $sequence->next_value;

            // 2. منطق إعادة التصفير بناءً على تاريخ المستند
            $shouldReset = false;

            if ($sequence->reset_frequency === 'yearly' && $sequence->current_year != $targetDate->year) {
                $shouldReset = true;
            } elseif ($sequence->reset_frequency === 'monthly' && ($sequence->current_month != $targetDate->month || $sequence->current_year != $targetDate->year)) {
                $shouldReset = true;
            }

            if ($shouldReset) {
                $nextValue = 1;
                DB::table('sequences')->where('id', $sequence->id)->update([
                    'current_year'  => $targetDate->year,
                    'current_month' => $targetDate->month,
                ]);
            }

            // 3. معالجة الصيغة (Pattern Parsing)
            $format = $sequence->format;

            // دمج البادئة (Prefix)
            if ($prefix) {
                if (str_contains($format, '{PREFIX}')) {
                    $format = str_replace('{PREFIX}', $prefix, $format);
                } else {
                    $format = $prefix . '-' . $format;
                }
            } else {
                $format = str_replace('{PREFIX}-', '', $format);
            }

            // استبدال متغيرات الوقت بناءً على تاريخ المستند المحدد
            $format = str_replace('{YM}', $targetDate->format('ym'), $format);
            $format = str_replace('{Y}', (string) $targetDate->year, $format);
            $format = str_replace('{y}', $targetDate->format('y'), $format);
            $format = str_replace('{m}', $targetDate->format('m'), $format);

            // استبدال العداد بالأصفار المحددة
            $format = preg_replace_callback('/\{([0]+)\}/', function ($matches) use ($nextValue) {
                $length = strlen($matches[1]);
                return str_pad((string) $nextValue, $length, '0', STR_PAD_LEFT);
            }, $format);

            // 4. تحديث العداد للقيمة التالية
            DB::table('sequences')->where('id', $sequence->id)->update([
                'next_value' => $nextValue + 1,
                'updated_at' => Carbon::now(),
            ]);

            return $format;
        });
    }
}