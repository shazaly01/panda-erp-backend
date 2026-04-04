<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\AccountService;
use App\Modules\Accounting\Http\Requests\StoreAccountRequest;
use App\Modules\Accounting\Http\Requests\UpdateAccountRequest;
use App\Modules\Accounting\Http\Resources\AccountResource;
use Illuminate\Http\Request;
use App\Modules\Accounting\Enums\AccountType;

class AccountController extends Controller
{
    public function __construct(
        protected AccountService $service
    ) {}

    /**
     * عرض شجرة الحسابات
     */
    public function index()
    {
        $this->authorize('viewAny', Account::class);

        // ميزة NestedSet: جلب البيانات مرتبة كشجرة
        // toTree() تقوم بترتيب الأبناء داخل الآباء
        $accounts = Account::defaultOrder()->get()->toTree();

        return AccountResource::collection($accounts);
    }

    /**
     * عرض حساب واحد
     */
    public function show(Account $account)
    {
        $this->authorize('view', $account);

        // تحميل الأبناء المباشرين إذا أردت
        $account->load('children');

        return new AccountResource($account);
    }

    /**
     * إنشاء حساب جديد
     */
    public function store(StoreAccountRequest $request)
    {
        // التحقق والصلاحيات تمت في الـ Request
        $account = $this->service->createAccount($request->validated());

        return response()->json([
            'message' => 'تم إنشاء الحساب بنجاح',
            'data' => new AccountResource($account),
        ], 201);
    }

    /**
     * تحديث حساب
     */
    public function update(UpdateAccountRequest $request, Account $account)
    {
        $updatedAccount = $this->service->updateAccount($account, $request->validated());

        return response()->json([
            'message' => 'تم تحديث الحساب بنجاح',
            'data' => new AccountResource($updatedAccount),
        ]);
    }

    /**
     * حذف حساب
     */
    public function destroy(Account $account)
    {
        $this->authorize('delete', $account);

        $this->service->deleteAccount($account);

        return response()->json([
            'message' => 'تم حذف الحساب بنجاح',
        ]);
    }


    /**
     * اقتراح كود الحساب الفرعي الجديد بناءً على الأب
     */
    public function suggestCode(Request $request)
    {
        $request->validate([
            'parent_id' => ['required', 'integer', 'exists:accounts,id']
        ]);

        try {
            $newCode = $this->service->generateCode((int) $request->parent_id);
            return response()->json(['code' => $newCode]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


    public function getTypes()
{
    // سنحول الـ Enum إلى مصفوفة بسيطة يفهمها الـ Dropdown في Vue
    $types = collect(AccountType::cases())->map(function ($type) {
        return [
            'id' => $type->value,
            'name' => $type->label()
        ];
    });

    return response()->json($types);
}
}
