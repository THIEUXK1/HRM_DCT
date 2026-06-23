<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends ApiController
{
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        return $this->success($roles);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'guard_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $guardName = $data['guard_name'] ?? 'web';
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => $guardName,
        ]);

        return $this->success($role, 201);
    }

    public function show(Role $role): JsonResponse
    {
        return $this->success($role->load('permissions'));
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,' . $role->id],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string'],
        ]);

        // Cập nhật tên vai trò
        $role->name = $data['name'];
        $role->save();

        // Đồng bộ danh sách quyền nếu có truyền lên
        if ($request->has('permissions')) {
            $role->syncPermissions($data['permissions']);
        }

        return $this->success($role->load('permissions'));
    }

    public function destroy(Role $role): JsonResponse
    {
        // Danh sách các vai trò lõi của hệ thống, không được xóa để tránh crash phân quyền ứng dụng
        $coreRoles = ['admin', 'employee', 'hr_manager', 'auditor', 'department_manager'];

        if (in_array($role->name, $coreRoles)) {
            return $this->error('Không thể xóa vai trò mặc định của hệ thống.', 403);
        }

        $role->delete();
        return $this->noContent();
    }
}
