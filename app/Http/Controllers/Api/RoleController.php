<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\Api\RoleResource;
use App\Services\RoleService;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
        private RoleService $roleService
    ) {
        // تطبيق الـ Policies تلقائياً
        $this->authorizeResource(Role::class, 'role');
    }

    public function index()
    {
        $roles = Role::with('permissions')->latest()->paginate(15);
        return RoleResource::collection($roles);
    }

    public function store(StoreRoleRequest $request)
    {
        $role = $this->roleService->createRole($request->validated());
        return new RoleResource($role);
    }

    public function show(Role $role)
    {
        return new RoleResource($role->load('permissions'));
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $role = $this->roleService->updateRole($role, $request->validated());
        return new RoleResource($role);
    }

    public function destroy(Role $role)
    {
        // حماية الأدوار النظامية الأساسية
        if (in_array($role->name, ['Super Admin', 'Admin', 'User', 'Employee', 'HR Manager'])) {
            abort(Response::HTTP_FORBIDDEN, 'لا يمكن حذف الأدوار الافتراضية للنظام.');
        }

        $role->delete();
        return response()->noContent();
    }

    /**
     * جلب كافة الصلاحيات مهيكلة للواجهة الأمامية
     */
    public function getAllPermissions()
    {
        $this->authorize('viewAny', Role::class);

        // تفويض العمل المعقد بالكامل للـ Service
        $data = $this->roleService->getStructuredPermissions();

        return response()->json($data);
    }
}
