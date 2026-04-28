<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\CostCenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;

class CostCenterService
{
    /**
     * إنشاء مركز تكلفة جديد مع التوليد الآلي للكود الهرمي
     */
    public function createCostCenter(array $data): CostCenter
    {
        return DB::transaction(function () use ($data) {
            // توليد الكود آلياً بناءً على الأب (إن وجد)
            $parentId = $data['parent_id'] ?? null;
            $data['code'] = $this->generateCode(isset($parentId) ? (int)$parentId : null);

            return CostCenter::create($data);
        });
    }

    /**
     * تحديث بيانات مركز التكلفة
     */
    public function updateCostCenter(CostCenter $costCenter, array $data): CostCenter
    {
        return DB::transaction(function () use ($costCenter, $data) {
            // 🚫 منع تعديل الكود يدوياً إذا تم إرساله بالخطأ للحفاظ على سلامة الشجرة
            unset($data['code']);

            $costCenter->update($data);
            return $costCenter->refresh();
        });
    }

    /**
     * حذف مركز التكلفة (مع الحماية المعمارية)
     */
    public function deleteCostCenter(CostCenter $costCenter): bool
    {
        // 1. حماية الشجرة: التحقق من وجود أبناء (Sub-Cost Centers)
        if ($costCenter->children()->exists()) {
            throw ValidationException::withMessages([
                'cost_center' => ["لا يمكن حذف مركز التكلفة ({$costCenter->name}) لأنه يحتوي على مراكز فرعية تابعة له."]
            ]);
        }

        // 2. حماية العمليات: التحقق من وجود قيود مالية مسجلة على هذا المركز
        // بافتراض وجود علاقة journalDetails في الموديل
        $hasTransactions = DB::table('journal_entry_details')->where('cost_center_id', $costCenter->id)->exists();
        if ($hasTransactions) {
            throw ValidationException::withMessages([
                'cost_center' => ["لا يمكن حذف المركز ({$costCenter->name}) لوجود حركات مالية مسجلة عليه. قم بإيقاف تنشيطه بدلاً من ذلك."]
            ]);
        }

        // 3. حماية الـ HR: التحقق من عدم ارتباطه بقسم تشغيلي في الموارد البشرية
        $isLinkedToDepartment = DB::table('departments')->where('cost_center_id', $costCenter->id)->exists();
        if ($isLinkedToDepartment) {
            throw ValidationException::withMessages([
                'cost_center' => ["لا يمكن حذف المركز ({$costCenter->name}) لأنه مربوط بقسم في إدارة الموارد البشرية."]
            ]);
        }

        return DB::transaction(function () use ($costCenter) {
            return $costCenter->delete();
        });
    }

    /**
     * توليد كود تلقائي ذكي لمراكز التكلفة
     * المستويات الرئيسية تبدأ بـ: 1, 2, 3...
     * المستويات الفرعية: ParentCode + 01 (خانتين لتسع 99 فرعاً تحت كل قسم)
     * مثال: 1 -> 101, 102
     */
    public function generateCode(?int $parentId): string
    {
        if (!$parentId) {
            // إذا كان مركزاً رئيسياً (لا يوجد أب)
            $lastRoot = CostCenter::whereNull('parent_id')
                ->orderByRaw('LENGTH(code) DESC')
                ->orderBy('code', 'desc')
                ->first();

            if ($lastRoot && is_numeric($lastRoot->code)) {
                return (string) ((int) $lastRoot->code + 1);
            }

            return '1'; // أول مركز تكلفة رئيسي في النظام
        }

        // إذا كان مركزاً فرعياً
        $parent = CostCenter::findOrFail($parentId);
        $parentCode = $parent->code;

        // البحث عن آخر كود مستخدم تحت هذا الأب
        $lastChild = CostCenter::where('parent_id', $parentId)
            ->orderByRaw('LENGTH(code) DESC')
            ->orderBy('code', 'desc')
            ->first();

        if ($lastChild) {
            $parentLen = strlen($parentCode);
            $lastChildCode = $lastChild->code;

            if (str_starts_with($lastChildCode, $parentCode)) {
                $suffix = substr($lastChildCode, $parentLen);

                if (is_numeric($suffix)) {
                    $nextSuffix = (int)$suffix + 1;
                    // إضافة صفر على اليسار إذا كان رقماً أحادياً (مثلاً 1 يصبح 01)
                    $newSuffix = str_pad((string)$nextSuffix, strlen($suffix), '0', STR_PAD_LEFT);
                    return $parentCode . $newSuffix;
                }
            }
        }

        // أول ابن لهذا المركز (يأخذ كود الأب + 01)
        return $parentCode . '01';
    }
}
