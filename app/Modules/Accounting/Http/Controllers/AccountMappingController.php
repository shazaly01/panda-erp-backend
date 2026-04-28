<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Modules\Accounting\Models\AccountMapping;
use App\Modules\Accounting\Models\Account; // 🌟 تمت الإضافة للتعامل مع الحسابات
use App\Modules\Accounting\Http\Requests\UpdateAccountMappingRequest;
use App\Modules\Accounting\Http\Resources\AccountMappingResource;
use App\Modules\Accounting\Http\Resources\AccountResource; // 🌟 تمت الإضافة لتنسيق البيانات
use App\Modules\Accounting\Services\AccountMappingService;
use Exception;

class AccountMappingController extends Controller
{
    // تعريف الخاصية لتكون متاحة في كل الدوال
    protected $mappingService;

    // حقن السيرفس عبر الـ Constructor
    public function __construct(AccountMappingService $mappingService)
    {
        $this->mappingService = $mappingService;
    }

    /**
     * عرض جميع إعدادات الربط الحالية
     */
    public function index(): JsonResponse
    {
        $mappings = AccountMapping::with('account')
            ->orderBy('id') // تم التعديل هنا للترتيب حسب المعرف
            ->get();

        return response()->json(AccountMappingResource::collection($mappings));
    }

    /**
     * تحديث توجيه حساب معين
     */
    public function update(UpdateAccountMappingRequest $request, $id): JsonResponse
    {
        $mapping = AccountMapping::findOrFail($id);

        // تحديث الحساب المرتبط
        $mapping->update([
            'account_id' => $request->account_id,
        ]);

        return response()->json([
            'message' => 'تم تحديث توجيه الحساب بنجاح',
            'data' => new AccountMappingResource($mapping->load('account'))
        ]);
    }

    /**
     * مسار مخصص لجلب الحسابات المسموح بها لموديول معين
     * GET /api/accounting/mappings/allowed-accounts/box_parent_account
     */
    public function allowedAccounts(string $key): JsonResponse
    {
        try {
            // الآن سيعمل هذا السطر لأننا قمنا بتعريف السيرفس أعلاه
            $accounts = $this->mappingService->getAllowedAccounts($key);

            return response()->json([
                'data' => $accounts
            ]);
        } catch (Exception $e) {
            // التعديل هنا: استخدم -> بدلاً من .
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * 🌟 الدالة الجديدة: جلب الحسابات المرشحة للربط وتغذية الواجهة الأمامية بها
     */
    public function candidates(): JsonResponse
    {
        // جلب الحسابات الفعالة والتي تقبل الحركات المالية فقط (لا نجلب الحسابات التجميعية/الآباء)
        $accounts = Account::where('is_transactional', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return response()->json(AccountResource::collection($accounts));
    }
}
