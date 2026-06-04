<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    /**
     * عرض قائمة المستخدمين مع البحث الذكي الموحد والفلترة بحسب الحالة عبر السيرفر
     */
    public function index(Request $request)
    {
        $query = User::with('roles');

        // الفلترة بحسب الحالة (active, pending, suspended)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // محرك البحث الذكي الموحد (بالاسم، أو الهاتف، أو اسم المستخدم)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(15);
        return UserResource::collection($users);
    }

    /**
     * إنشاء حساب موظف جديد من طرف المشرف بدون كلمة مرور وبحالة معلقة (بانتظار الـ OTP)
     */
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $userData = array_merge($validated, [
                'status' => 'pending',
            ]);

            $user = User::create($userData);

            if (!empty($validated['roles'])) {
                $user->assignRole($validated['roles']);
            }

            DB::commit();

            return new UserResource($user->load('roles'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create user.', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(User $user)
    {
        return new UserResource($user->load('roles'));
    }

    /**
     * تحديث البيانات الوظيفية والربط المحاسبي للموظف
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $user->update($validated);

            if (isset($validated['roles'])) {
                $user->syncRoles($validated['roles']);
            }

            DB::commit();

            return new UserResource($user->fresh()->load('roles'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update user.', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(User $user)
    {
        if ($user->hasRole('Super Admin')) {
            abort(Response::HTTP_FORBIDDEN, 'Cannot delete a Super Admin user.');
        }

        $user->delete();
        return response()->noContent();
    }

    /**
     * اعتماد حساب معلق وضخ الصلاحيات والافتراضيات المحاسبية له بواسطة المشرف
     */
    public function approve(Request $request, User $user)
    {
        $this->authorize('approve', User::class);

        $validated = $request->validate([
            'roles'                   => 'required|array',
            'roles.*'                 => 'string|exists:roles,name,guard_name,api',
            'default_cost_center_id'  => 'nullable|integer|exists:cost_centers,id',
            'default_box_id'          => 'nullable|integer|exists:boxes,id',
            'default_bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
        ]);

        DB::beginTransaction();
        try {
            $user->update([
                'status'                  => 'active',
                'default_cost_center_id'  => $validated['default_cost_center_id'] ?? $user->default_cost_center_id,
                'default_box_id'          => $validated['default_box_id'] ?? $user->default_box_id,
                'default_bank_account_id' => $validated['default_bank_account_id'] ?? $user->default_bank_account_id,
            ]);

            $user->syncRoles($validated['roles']);

            DB::commit();

            return new UserResource($user->load('roles'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to approve user.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * تعليق أو إعادة تنشيط حساب المستخدم تلقائياً وطرد جلساته النشطة لحماية النظام
     */
    public function toggleStatus(Request $request, User $user)
    {
        // حماية حساب الطوارئ الحرج ومنع تعليقه تحت أي ظرف
        if ($user->hasRole('Super Admin')) {
            return response()->json(['message' => 'لا يمكن تعليق حساب الـ Super Admin الثابت للطوارئ.'], 403);
        }

        DB::beginTransaction();
        try {
            if ($user->status === 'active') {
                $user->update(['status' => 'suspended']);

                // 🔥 سحر الأمان: حذف جميع توكنات الجلسات الفعالة للمستخدم ليتم طرده فوراً عبر الـ Interceptor
                $user->tokens()->delete();

                $message = "تم تعليق حساب الموظف '{$user->full_name}' بنجاح وإلغاء كافة صلاحيات دخوله الحالية.";
            } else if ($user->status === 'suspended') {
                $user->update(['status' => 'active']);
                $message = "تم إعادة تنشيط حساب الموظف '{$user->full_name}' بنجاح ويمكنه الدخول الآن.";
            } else {
                return response()->json(['message' => 'لا يمكن تغيير حالة حساب لم يتم اعتماده وتفعيله مسبقاً.'], 400);
            }

            DB::commit();
            return response()->json([
                'message' => $message,
                'user'    => new UserResource($user->load('roles'))
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to toggle user status.', 'error' => $e->getMessage()], 500);
        }
    }
}
