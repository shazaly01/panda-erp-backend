<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class SequenceService
{
    /**
     * توليد الرقم التالي بناءً على الصيغة المخزنة في قاعدة البيانات
     * * @param string $modelClass اسم الموديل (مثال: JournalEntry::class)
     * @param int|null $branchId رقم الفرع (اختياري)
     * @return string الرقم النهائي المنسق (مثال: JE-2025-0001)
     */
    public function generateNumber(string $modelClass, ?int $branchId = null, ?string $prefix = null): string
    {
        return DB::transaction(function () use ($modelClass, $branchId) {

            // 1. جلب إعدادات التسلسل مع قفل الصف (Lock) لمنع تضارب المستخدمين
            $sequence = DB::table('sequences')
                ->where('model', $modelClass)
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->first();

            // إذا لم يتم إعداد التسلسل مسبقاً، نوقف العملية
            if (!$sequence) {
                throw new Exception("لم يتم ضبط إعدادات التسلسل لـ: {$modelClass}");
            }

            $now = Carbon::now();
            $nextValue = $sequence->next_value;

            // 2. منطق إعادة التصفير (Reset Logic)
            $shouldReset = false;

            // إذا كان التصفير سنوياً ودخلنا سنة جديدة
            if ($sequence->reset_frequency === 'yearly' && $sequence->current_year != $now->year) {
                $shouldReset = true;
            }
            // إذا كان التصفير شهرياً ودخلنا شهراً جديداً
            elseif ($sequence->reset_frequency === 'monthly' && $sequence->current_month != $now->month) {
                $shouldReset = true;
            }

            if ($shouldReset) {
                $nextValue = 1;
                // تحديث مؤشرات الوقت
                DB::table('sequences')->where('id', $sequence->id)->update([
                    'current_year' => $now->year,
                    'current_month' => $now->month,
                ]);
            }

            // 3. معالجة الصيغة (Pattern Parsing)
            // الصيغة الخام: JE-{Y}-{00000}
            $format = $sequence->format;

            // استبدال متغيرات الوقت
            $format = str_replace('{Y}', (string)$now->year, $format); // 2025
            $format = str_replace('{y}', $now->format('y'), $format);   // 25
            $format = str_replace('{m}', $now->format('m'), $format);   // 05 (الشهر)

            // استبدال الرقم المتسلسل (التعامل مع الأصفار)
            // نبحث عن أي نمط مثل {000} أو {00000}
            $format = preg_replace_callback('/\{([0]+)\}/', function ($matches) use ($nextValue) {
                $length = strlen($matches[1]); // طول الأصفار المطلوبة
                return str_pad((string)$nextValue, $length, '0', STR_PAD_LEFT);
            }, $format);

            // 4. حفظ الرقم القادم
            DB::table('sequences')->where('id', $sequence->id)->update([
                'next_value' => $nextValue + 1,
                'updated_at' => $now,
            ]);

            return $format;
        });
    }
}
