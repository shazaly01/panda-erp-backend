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
     * * @param string $documentCode كود المستند (مثال: acc_journal_entry)
     * @param int|null $branchId رقم الفرع (لفصل العدادات حسب الفرع إن لزم)
     * @param string|null $prefix بادئة الفرع (مثال: JD أو RY)
     * @return string الرقم النهائي المنسق (مثال: JD-JE-2026-00001)
     */
    public function generateNumber(string $documentCode, ?int $branchId = null, ?string $prefix = null): string
    {
        return DB::transaction(function () use ($documentCode, $branchId, $prefix) {

            // 1. جلب إعدادات التسلسل مع قفل الصف (Lock) لمنع تضارب المستخدمين في نفس اللحظة
            $sequence = DB::table('sequences')
                ->where('model', $documentCode) // نستخدم عمود model للبحث عن documentCode كما اتفقنا
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                throw new Exception("لم يتم ضبط إعدادات التسلسل للمستند: {$documentCode}");
            }

            $now = Carbon::now();
            $nextValue = $sequence->next_value;

            // 2. منطق إعادة التصفير (Reset Logic) المعزز
            $shouldReset = false;

            if ($sequence->reset_frequency === 'yearly' && $sequence->current_year != $now->year) {
                $shouldReset = true;
            }
            // 🌟 التحديث: التصفير الشهري يجب أن يراقب تغير الشهر أو تغير السنة
            elseif ($sequence->reset_frequency === 'monthly' && ($sequence->current_month != $now->month || $sequence->current_year != $now->year)) {
                $shouldReset = true;
            }

            if ($shouldReset) {
                $nextValue = 1;
                DB::table('sequences')->where('id', $sequence->id)->update([
                    'current_year'  => $now->year,
                    'current_month' => $now->month,
                ]);
            }

            // 3. معالجة الصيغة (Pattern Parsing)
            $format = $sequence->format;

            // 🌟 التحديث: دمج البادئة (Prefix) إذا تم تمريرها
            // إذا كانت الصيغة تحتوي على {PREFIX} نستبدلها، وإلا نضعها في بداية الرقم آلياً
            if ($prefix) {
                if (str_contains($format, '{PREFIX}')) {
                    $format = str_replace('{PREFIX}', $prefix, $format);
                } else {
                    $format = $prefix . '-' . $format; // مثال: RY-PAY-2026-0001
                }
            } else {
                // تنظيف الصيغة من كلمة {PREFIX} إذا لم يتم تمرير بادئة
                $format = str_replace('{PREFIX}-', '', $format);
            }

            // 🌟 التحديث: إضافة مترجم الصيغة الشهرية المدمجة {YM} (مثل 2604)
            $format = str_replace('{YM}', $now->format('ym'), $format);

            // استبدال متغيرات الوقت العادية
            $format = str_replace('{Y}', (string)$now->year, $format); // 2026
            $format = str_replace('{y}', $now->format('y'), $format);  // 26
            $format = str_replace('{m}', $now->format('m'), $format);  // 04

            // استبدال الرقم المتسلسل (التعامل مع الأصفار الديناميكية)
            $format = preg_replace_callback('/\{([0]+)\}/', function ($matches) use ($nextValue) {
                $length = strlen($matches[1]);
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
