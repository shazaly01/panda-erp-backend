<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function index()
    {
        $users = User::with('roles')->latest()->paginate(15);
        return UserResource::collection($users);
    }
public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            // ✅ الحل: دمج كلمة المرور المشفرة مع باقي البيانات الموثقة تلقائياً
            $userData = array_merge($validated, [
                'password' => Hash::make($validated['password']),
            ]);

            $user = User::create($userData);

            $user->assignRole($validated['roles']);
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

   public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $userData = $validated;

            // تحديث كلمة المرور فقط إذا تم إرسالها، وإلا احذفها من المصفوفة لكي لا تصبح null
            if (!empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            } else {
                unset($userData['password']);
            }

            $user->update($userData);

            $user->syncRoles($validated['roles']);
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
}
