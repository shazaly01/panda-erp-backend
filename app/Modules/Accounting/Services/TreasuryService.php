<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Box;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Enums\AccountNature;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * TreasuryService
 * مسؤول عن إدارة العمليات التشغيلية للخزائن والبنوك والربط مع دليل الحسابات
 */
class TreasuryService
{
    public function __construct(
        protected AccountService $accountService,
        protected AccountMappingService $mappingService
    ) {}

    // =========================================================================
    // عمليات الخزائن (Boxes / Treasuries)
    // =========================================================================

    /**
     * إنشاء خزينة جديدة
     * يدعم الربط اليدوي بحساب موجود أو الإنشاء التلقائي لحساب جديد
     */
    public function createBox(array $data): Box
    {
        return DB::transaction(function () use ($data) {

            // 1. تحديد الحساب المالي المرتبط
            if (!empty($data['account_id'])) {
                // مدرسة الربط اليدوي: نستخدم الحساب المختار من الواجهة
                $accountId = (int) $data['account_id'];
            } else {
                // مدرسة الإنشاء التلقائي: نطلب من AccountService إنشاء حساب فرعي
                // 🌟 التعديل هنا: استخدام المفتاح المعياري الجديد treasury_box_parent
                $parentAccountId = $this->mappingService->getAccountId('treasury_box_parent', $data['branch_id'] ?? null);

                $newAccount = $this->accountService->createAccount([
                    'name'             => $data['name'],
                    'code'             => $this->accountService->generateCode($parentAccountId),
                    'parent_id'        => $parentAccountId,
                    'currency_id'      => $data['currency_id'],
                    'nature'           => AccountNature::Debit, // الخزينة دائماً أصل مدين
                    'type'             => 'asset',
                    'is_transactional' => true,
                    'is_active'        => $data['is_active'] ?? true,
                ]);
                $accountId = $newAccount->id;
            }

            // 2. إنشاء سجل الخزينة وربطه بالحساب المالي
            return Box::create([
                'name'         => $data['name'],
                'account_id'   => $accountId,
                'branch_id'    => $data['branch_id'] ?? null,
                'currency_id'  => $data['currency_id'],
                'description'  => $data['description'] ?? null,
                'is_active'    => $data['is_active'] ?? true,
            ]);
        });
    }

    /**
     * تحديث بيانات الخزينة
     * يقوم بمزامنة الاسم مع دليل الحسابات لضمان التطابق
     */
    public function updateBox(Box $box, array $data): Box
    {
        return DB::transaction(function () use ($box, $data) {
            // تحديث بيانات الخزينة
            $box->update($data);

            // إذا كانت الخزينة مرتبطة بحساب، نحدث اسم الحساب وحالته
            if ($box->account_id) {
                $box->account->update([
                    'name'      => $data['name'] ?? $box->account->name,
                    'is_active' => $data['is_active'] ?? $box->account->is_active,
                ]);
            }

            return $box;
        });
    }

    /**
     * حذف الخزينة
     * يتم التحقق من عدم وجود حركات مالية قبل الحذف عبر AccountService
     */
    public function deleteBox(Box $box): bool
    {
        return DB::transaction(function () use ($box) {
            $account = $box->account;

            // حذف سجل الخزينة (Soft Delete)
            $box->delete();

            // محاولة حذف الحساب المالي المرتبط
            // ملاحظة: الـ AccountService سيرفض الحذف إذا وُجدت قيود محاسبية
            if ($account) {
                return $this->accountService->deleteAccount($account);
            }

            return true;
        });
    }

    // =========================================================================
    // عمليات الحسابات البنكية (Bank Accounts)
    // =========================================================================

    /**
     * إنشاء حساب بنكي جديد
     */
    public function createBankAccount(array $data): BankAccount
    {
        return DB::transaction(function () use ($data) {

            if (!empty($data['account_id'])) {
                $accountId = (int) $data['account_id'];
            } else {
                // 🌟 التعديل هنا: استخدام المفتاح المعياري الجديد treasury_bank_parent
                $parentAccountId = $this->mappingService->getAccountId('treasury_bank_parent', $data['branch_id'] ?? null);

                // تنسيق اسم الحساب المالي (اسم البنك + رقم الحساب) لسهولة التعرف عليه في الشجرة
                $accountName = $data['bank_name'] . ' - ' . $data['account_number'];

                $newAccount = $this->accountService->createAccount([
                    'name'             => $accountName,
                    'code'             => $this->accountService->generateCode($parentAccountId),
                    'parent_id'        => $parentAccountId,
                    'currency_id'      => $data['currency_id'],
                    'nature'           => AccountNature::Debit,
                    'type'             => 'asset',
                    'is_transactional' => true,
                    'is_active'        => $data['is_active'] ?? true,
                ]);
                $accountId = $newAccount->id;
            }

            return BankAccount::create([
                'bank_name'      => $data['bank_name'],
                'account_name'   => $data['account_name'],
                'account_number' => $data['account_number'],
                'iban'           => $data['iban'] ?? null,
                'account_id'     => $accountId,
                'currency_id'    => $data['currency_id'],
                'branch_id'      => $data['branch_id'] ?? null,
                'is_active'      => $data['is_active'] ?? true,
            ]);
        });
    }

    /**
     * تحديث الحساب البنكي
     */
    public function updateBankAccount(BankAccount $bankAccount, array $data): BankAccount
    {
        return DB::transaction(function () use ($bankAccount, $data) {
            $bankAccount->update($data);

            if ($bankAccount->account_id) {
                $newBankName = $data['bank_name'] ?? $bankAccount->bank_name;
                $newAccNumber = $data['account_number'] ?? $bankAccount->account_number;

                $bankAccount->account->update([
                    'name'      => $newBankName . ' - ' . $newAccNumber,
                    'is_active' => $data['is_active'] ?? $bankAccount->account->is_active,
                ]);
            }

            return $bankAccount;
        });
    }

    /**
     * حذف الحساب البنكي
     */
    public function deleteBankAccount(BankAccount $bankAccount): bool
    {
        return DB::transaction(function () use ($bankAccount) {
            $account = $bankAccount->account;

            $bankAccount->delete();

            if ($account) {
                return $this->accountService->deleteAccount($account);
            }

            return true;
        });
    }
}
