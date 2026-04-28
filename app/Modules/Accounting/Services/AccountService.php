<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;

class AccountService
{
    /**
     * إنشاء حساب جديد
     */
    public function createAccount(array $data): Account
    {
        return DB::transaction(function () use ($data) {
            // تحديد المستوى (Level) بناءً على الأب
            $level = 1;
            if (isset($data['parent_id']) && $data['parent_id']) {
                $parent = Account::find($data['parent_id']);
                if ($parent) {
                    $level = $parent->level + 1;
                }
            }

            // التأكد من عدم تكرار الكود
            if (Account::where('code', $data['code'])->exists()) {
                throw ValidationException::withMessages([
                    'code' => ["كود الحساب ({$data['code']}) مستخدم بالفعل."]
                ]);
            }

            return Account::create([
                'name'                 => $data['name'],
                'code'                 => $data['code'],
                'parent_id'            => $data['parent_id'] ?? null,
                'currency_id'          => $data['currency_id'] ?? null,
                'nature'               => $data['nature'],           // مدين/دائن
                'type'                 => $data['type'],             // أصول/خصوم...
                'level'                => $level,
                'is_transactional'     => $data['is_transactional'] ?? true, // هل يقبل قيود؟
                // 🌟 تصحيح التناسق: إضافة حقل مركز التكلفة عند الإنشاء
                'requires_cost_center' => $data['requires_cost_center'] ?? false,
                'is_active'            => $data['is_active'] ?? true,
                'description'          => $data['description'] ?? null,
            ]);
        });
    }

    public function updateAccount(Account $account, array $data): Account
    {
        $hasTransactions = $account->details()->exists();

        if ($hasTransactions && isset($data['code']) && $data['code'] !== $account->code) {
             throw ValidationException::withMessages([
                'code' => ['لا يمكن تغيير كود حساب عليه عمليات مالية مسجلة.']
            ]);
        }

        $account->update([
            'name'                 => $data['name'],
            'description'          => $data['description'] ?? $account->description,
            'is_active'            => $data['is_active'] ?? $account->is_active,
            'nature'               => $data['nature'] ?? $account->nature,
            'type'                 => $data['type'] ?? $account->type,
            'is_transactional'     => isset($data['is_transactional']) ? (bool)$data['is_transactional'] : $account->is_transactional,
            'requires_cost_center' => isset($data['requires_cost_center']) ? (bool)$data['requires_cost_center'] : $account->requires_cost_center,
            'code'                 => (!$hasTransactions && isset($data['code'])) ? $data['code'] : $account->code,
        ]);

        return $account;
    }

    /**
     * حذف الحساب
     */
    public function deleteAccount(Account $account): bool
    {
        // 1. التحقق من وجود أبناء (Sub-Accounts)
        if ($account->children()->exists()) {
            throw ValidationException::withMessages([
                'account' => ["لا يمكن حذف الحساب ({$account->name}) لأنه حساب رئيسي يحتوي على حسابات فرعية."]
            ]);
        }

        // 2. التحقق من وجود قيود مالية (Transactions)
        if ($account->details()->exists()) {
            throw ValidationException::withMessages([
                'account' => ["لا يمكن حذف الحساب ({$account->name}) لوجود قيود مالية مرتبطة به. قم بتعطيله بدلاً من حذفه."]
            ]);
        }

        // 🌟 3. الحماية السيادية: التحقق من وجود ارتباط في إعدادات النظام (Account Mappings)
        $isMapped = DB::table('account_mappings')->where('account_id', $account->id)->exists();
        if ($isMapped) {
            throw ValidationException::withMessages([
                'account' => ["لا يمكن حذف الحساب ({$account->name}) لأنه مربوط كمفتاح أساسي (System Mapping) في إعدادات تشغيل الـ ERP. الرجاء فك الربط أولاً."]
            ]);
        }

        // 4. التحقق من الارتباطات الأخرى (خزائن، بنوك)
        // إذا كان هناك قيود في SQL ستمنع الحذف وترمي خطأ 500، وهو أمان إضافي ممتاز.

        return $account->delete();
    }

    /**
     * توليد كود تلقائي ذكي
     * النمط: ParentCode + 001 (ثلاث خانات)
     * مثال: الأب 101 -> الابن الأول 101001، الثاني 101002
     */
    public function generateCode(?int $parentId): string
    {
        // إذا لم يكن هناك أب، لا يمكن التوليد (لأن المستوى الأول يُدخل يدوياً عادة)
        if (!$parentId) {
            throw new Exception("يجب تحديد حساب رئيسي لتوليد كود فرعي.");
        }

        $parentAccount = Account::findOrFail($parentId);
        $parentCode = $parentAccount->code;

        // البحث عن آخر كود مستخدم تحت هذا الأب
        $lastChild = Account::where('parent_id', $parentId)
            ->orderByRaw('LENGTH(code) DESC') // لضمان الترتيب الصحيح للأطوال المختلفة
            ->orderBy('code', 'desc')
            ->first();

        if ($lastChild) {
            // استخراج الرقم الأخير وزيادته
            $parentLen = strlen($parentCode);
            $lastChildCode = $lastChild->code;

            // التأكد أن الكود يبدأ بكود الأب فعلاً (حماية من البيانات الفاسدة)
            if (str_starts_with($lastChildCode, $parentCode)) {
                $suffix = substr($lastChildCode, $parentLen); // نأخذ الجزء المضاف فقط

                if (is_numeric($suffix)) {
                    $nextSuffix = (int)$suffix + 1;
                    // إعادة التنسيق مع حشو أصفار (Padding) للحفاظ على الطول
                    $newSuffix = str_pad((string)$nextSuffix, strlen($suffix), '0', STR_PAD_LEFT);
                    return $parentCode . $newSuffix;
                }
            }
        }

        // إذا كان أول ابن، أو فشل التحليل السابق
        // نستخدم 3 خانات (001) ليعطينا سعة 999 حساب فرعي
        return $parentCode . '001';
    }
}
