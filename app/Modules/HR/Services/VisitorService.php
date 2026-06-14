<?php

namespace App\Modules\HR\Services;

use Exception;
use Illuminate\Support\Str;
use App\Modules\HR\Models\Employee;
use Illuminate\Support\Facades\DB;
use App\Modules\HR\Models\Visitor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class VisitorService
{
    /**
     * جلب قائمة الزوار مع الفلترة المتقدمة لشاشة الأمن ولوحة التحكم.
     */
    public function getAllVisitors(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Visitor::query()->with(['employee', 'gatekeeper']);

        // فلترة تلقائية لزوار اليوم إذا تم طلب ذلك (مخصص لشاشة الأمن الأساسية)
       // 🌟 تحديث منطق الفلترة ليكون مرناً ويتفادى فجوات فروق التوقيت بين الأجهزة
if (isset($filters['today_only']) && $filters['today_only'] == true) {
    // إذا أرسلت الواجهة تاريخاً محدداً نعتمد عليه، وإلا نعود لتاريخ اليوم الحالي للسيرفر كـ Fallback
    $targetDate = $filters['visit_date'] ?? now()->toDateString();
    $query->whereDate('visit_date', $targetDate);
} elseif (!empty($filters['visit_date'])) {
    $query->whereDate('visit_date', $filters['visit_date']);
}

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // البحث الذكي بالاسم، الهاتف، أو الرقم القومي
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('national_id', 'like', "%{$search}%");
            });
        }

       return $query->latest()->paginate($perPage)->withQueryString();
    }

    /**
     * تسجيل زائر جديد (مع دعم تاريخ الزيارة المتوقع).
     */
    public function registerVisitor(array $data): Visitor
    {
        return DB::transaction(function () use ($data) {
            // توليد رمز فريد للـ QR Code
            $data['qr_token'] = 'VIS-' . Str::upper(Str::random(10)) . '-' . time();
            $data['status'] = 'pending';

            // إذا لم يتم تحديد تاريخ الزيارة، يتم اعتباره لليوم تلقائياً
            $data['visit_date'] = $data['visit_date'] ?? now()->toDateString();

            return Visitor::create($data);
        });
    }

    /**
     * تحديث بيانات الزائر.
     */
    public function updateVisitor(Visitor $visitor, array $data): Visitor
    {
        return DB::transaction(function () use ($visitor, $data) {
            $visitor->update($data);
            return $visitor;
        });
    }

    /**
     * تسجيل دخول الزائر عند البوابة (دعم البحث بالـ ID أو بالـ Token لمرونة الأزرار والباركود).
     */
    public function processCheckIn(array $identifier, int $gatekeeperId): Visitor
    {
        return DB::transaction(function () use ($identifier, $gatekeeperId) {
            $query = Visitor::query();

            if (!empty($identifier['id'])) {
                $visitor = $query->findOrFail($identifier['id']);
            } else {
                $visitor = $query->where('qr_token', $identifier['qr_token'])->firstOrFail();
            }

            // حل ثغرة الأمان الزمنية: التحقق من أن الزيارة مجدولة لتاريخ اليوم
            if ($visitor->visit_date !== now()->toDateString()) {
                throw new Exception('لا يمكن تسجيل الدخول؛ هذا الرمز مخصص لزيارة بتاريخ وموعد آخر.');
            }

            if ($visitor->status !== 'pending') {
                throw new Exception('لا يمكن تسجيل دخول هذا الزائر، الحالة الحالية لا تسمح بالدخول.');
            }

            $visitor->update([
                'status' => 'checked_in',
                'checked_in_at' => now(),
                'gatekeeper_id' => $gatekeeperId
            ]);

            return $visitor;
        });
    }

    /**
     * تسجيل خروج الزائر عند البوابة (دعم البحث بالـ ID أو بالـ Token).
     */
    public function processCheckOut(array $identifier, int $gatekeeperId): Visitor
    {
        return DB::transaction(function () use ($identifier, $gatekeeperId) {
            $query = Visitor::query();

            if (!empty($identifier['id'])) {
                $visitor = $query->findOrFail($identifier['id']);
            } else {
                $visitor = $query->where('qr_token', $identifier['qr_token'])->firstOrFail();
            }

            if ($visitor->status !== 'checked_in') {
                throw new Exception('لا يمكن تسجيل خروج الزائر، لم يتم إثبات دخوله المنشأة بعد.');
            }

            $visitor->update([
                'status' => 'checked_out',
                'checked_out_at' => now(),
                'gatekeeper_id' => $gatekeeperId
            ]);

            return $visitor;
        });
    }

    /**
     * حذف سجل الزائر (Soft Delete).
     */
    public function deleteVisitor(Visitor $visitor): bool
    {
        return DB::transaction(function () use ($visitor) {
            return $visitor->delete();
        });
    }

  /**
     * البحث المقيد والآمن عن الموظفين المستضيفين (Autocomplete) لحماية الخصوصية.
     */
    public function searchSecureHosts(string $searchTerm): Collection
    {
        if (strlen($searchTerm) < 3) {
            return new Collection();
        }

        // جلب المعرف والاسم عبر الـ Alias لحماية الهيكل وتوافق الواجهة الأمامية
        return Employee::query()
            ->select('id', 'full_name as name', 'department_id')
            ->with('department:id,name')
            ->where('full_name', 'like', "%{$searchTerm}%")
            ->limit(10)
            ->get();
    }



    /**
     * معالجة التحقق الذكي لبطاقات الزوار عند البوابة الخارجية بدون تشتت أو صلاحيات معقدة.
     */
    public function getVisitorStatusForGate(string $token): array
    {
        // جلب الزائر مع الموظف المستضيف بناءً على التوكن الفريد
        $visitor = Visitor::where('qr_token', $token)->with('employee')->first();

        // 1. التحقق من وجود السجل في النظام
        if (!$visitor) {
            return [
                'allowed' => false,
                'message' => 'بطاقة الدخول غير صالحة، أو الرمز البرمجي مزور وغير مدرج بالنظام!'
            ];
        }

        // 2. التحقق من مطابقة تاريخ الزيارة لليوم الحالي للسيرفر منعا للتلاعب الزمني
        if ($visitor->visit_date !== now()->toDateString()) {
            return [
                'allowed' => false,
                'message' => "هذه البطاقة غير صالحة لليوم؛ مخصصة لزيارة مجدولة بتاريخ سابق أو لاحق وهو: {$visitor->visit_date}"
            ];
        }

        // 3. التحقق من حالات الدخول المسبق
        if ($visitor->status === 'checked_in') {
            return [
                'allowed' => false,
                'message' => 'تنبيه أمني: الزائر مسجل حالة (داخل المنشأة) بالفعل حالياً ولا يمكن إعادة استخدام الرمز!'
            ];
        }

        // 4. التحقق من حالات الخروج والانتهاء
        if ($visitor->status === 'checked_out') {
            return [
                'allowed' => false,
                'message' => 'بطاقة منتهية الصلاحية: تم تسجيل خروج هذا الزائر من المنشأة سابقاً وانتهت دورتها.'
            ];
        }

        // 5. في حال اجتياز كافة القيود الأمنية بنجاح
        return [
            'allowed' => true,
            'message' => 'الزيارة معتمدة وصالحة؛ يرجى السماح للزائر بالدخول وتوجيهه للمستضيف.',
            'visitor' => [
                'name' => $visitor->full_name ?? $visitor->name,
                'company' => $visitor->company_from ?? 'زيارة شخصية',
                'host' => $visitor->employee?->full_name ?? 'غير محدد'
            ]
        ];
    }
}
