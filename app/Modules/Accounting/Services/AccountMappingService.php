<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\AccountMapping;
use Exception;
use Illuminate\Support\Facades\Cache; // يفضل استخدام الكاش لأن هذه الإعدادات لا تتغير كثيراً

class AccountMappingService
{
    /**
     * الحصول على معرف الحساب بناءً على المفتاح
     * مثال: getAccountId('sales_revenue', 1)
     */
    public function getAccountId(string $key, ?int $branchId = null): int
    {
        // استخدام الكاش لتحسين الأداء (اختياري ولكن موصى به)
        $cacheKey = "account_mapping_{$key}_{$branchId}";

        return Cache::remember($cacheKey, 60 * 60, function () use ($key, $branchId) {

            // 1. محاولة العثور على توجيه خاص بالفرع
            $mapping = AccountMapping::query()
                ->where('key', $key)
                ->where(function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId)
                          ->orWhereNull('branch_id'); // أو العام
                })
                ->orderByDesc('branch_id') // الأولوية للفرع المحدد ثم العام
                ->first();

            if (!$mapping) {
                // إذا لم نجد توجيهاً، يجب إيقاف العملية فوراً
                throw new Exception("لم يتم ضبط التوجيه المحاسبي للمفتاح: {$key}");
            }

            return $mapping->account_id;
        });
    }

    /**
     * إنشاء أو تحديث توجيه (للإعدادات)
     */
    public function setMapping(string $key, int $accountId, ?int $branchId = null, ?string $name = null): AccountMapping
    {
        Cache::forget("account_mapping_{$key}_{$branchId}"); // مسح الكاش

        return AccountMapping::updateOrCreate(
            ['key' => $key, 'branch_id' => $branchId],
            ['account_id' => $accountId, 'name' => $name]
        );
    }



    /**
 * جلب الحسابات الفرعية (الحركية) المسموح بالربط عليها بناءً على مفتاح الخريطة
 * هذا ما سيجعل المستخدم يرى فقط حسابات "النقدية" عند إضافة خزينة
 */
public function getAllowedAccounts(string $key, ?int $branchId = null)
{
    // 1. جلب الحساب الأب من الخريطة (مثلاً حساب النقدية)
    $parentId = $this->getAccountId($key, $branchId);

    // 2. جلب الأبناء الذين يتبعون هذا الأب ويقبلون الحركات (transactional)
    // نستخدم query لبناء الفلترة
    return \App\Modules\Accounting\Models\Account::where('parent_id', $parentId)
        ->where('is_transactional', true)
        ->get();
}
}
