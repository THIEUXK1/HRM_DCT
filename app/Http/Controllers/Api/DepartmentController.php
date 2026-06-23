<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\DepartmentRequest;
use App\Models\Department;
use App\Services\Hr\CompanyOrgSetupService;
use App\Support\CompanyContext;
use App\Support\OrgStructureScope;
use Illuminate\Http\Request;

class DepartmentController extends ApiController
{
    public function __construct()
    {
        $this->authorizeResource(Department::class, 'department');
    }

    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = Department::with(['branch.company', 'manager', 'parent'])->orderBy('name');
        OrgStructureScope::applyDepartmentScope($query);

        if ($request->filled('branch_id')) {
            $branchId = $request->integer('branch_id');
            if (! OrgStructureScope::branchBelongsToCompany($branchId)) {
                return $this->error('Chi nhánh không thuộc công ty đang chọn.', 422);
            }
            $query->where('branch_id', $branchId);
        }

        return $this->success($query->get());
    }

    public function store(DepartmentRequest $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();

        $companyId = CompanyContext::id();
        if ($companyId && empty($data['branch_id'])) {
            $data['branch_id'] = app(CompanyOrgSetupService::class)
                ->resolveBranchIdForCompany($companyId);
        }

        $department = Department::create($data);

        return $this->success($department->load(['branch.company', 'manager', 'parent']), 201);
    }

    public function show(Department $department): \Illuminate\Http\JsonResponse
    {
        return $this->success($department->load(['branch', 'manager', 'parent']));
    }

    public function update(DepartmentRequest $request, Department $department): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();

        $department->update($data);

        return $this->success($department);
    }

    public function destroy(Department $department): \Illuminate\Http\JsonResponse
    {
        $department->delete();

        return $this->noContent();
    }
}
