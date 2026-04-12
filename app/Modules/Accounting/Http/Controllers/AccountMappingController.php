<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Modules\Accounting\Models\AccountMapping;
// سننشئ هذا الـ Request في الخطوة التالية
use App\Modules\Accounting\Http\Requests\UpdateAccountMappingRequest;
// سننشئ هذا الـ Resource في الخطوة التالية
use App\Modules\Accounting\Http\Resources\AccountMappingResource;
use App\Modules\Accounting\Services\AccountMappingService; // تأكد من استدعاء السيرفس
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
}
