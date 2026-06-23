<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\BranchRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Services\Hr\CompanyOrgSetupService;
use App\Support\CompanyContext;
use App\Support\OrgStructureScope;

class BranchController extends ApiController
{
    public function __construct()
    {
        $this->authorizeResource(Branch::class, 'branch');
    }

    public function index(): \Illuminate\Http\JsonResponse
    {
        $query = Branch::with('company')->orderBy('name');
        OrgStructureScope::applyBranchScope($query);

        return $this->success($query->get());
    }

    public function store(BranchRequest $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();

        $branch = Branch::create($data);

        return $this->success($branch, 201);
    }

    public function show(Branch $branch): \Illuminate\Http\JsonResponse
    {
        return $this->success($branch->load('company', 'manager'));
    }

    public function update(BranchRequest $request, Branch $branch): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();

        $branch->update($data);

        return $this->success($branch);
    }

    public function destroy(Branch $branch): \Illuminate\Http\JsonResponse
    {
        $branch->delete();

        return $this->noContent();
    }

    /**
     * Tạo chi nhánh «Trụ sở chính» nếu công ty chưa có chi nhánh nào.
     */
    public function ensureDefault(): \Illuminate\Http\JsonResponse
    {
        $companyId = CompanyContext::id();
        if (! $companyId) {
            return response()->json(['message' => 'Chưa chọn công ty (header X-Company-Id).'], 422);
        }

        $this->authorize('create', Branch::class);

        $company = Company::query()->findOrFail($companyId);
        $branch = app(CompanyOrgSetupService::class)->ensureDefaultBranch($company);

        return $this->success($branch);
    }
}
