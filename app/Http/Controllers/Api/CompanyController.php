<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use App\Services\Company\CompanyPolicyTemplateService;
use App\Services\Hr\CompanyOrgSetupService;
use App\Support\CompanyContext;

class CompanyController extends ApiController
{
    public function __construct()
    {
        $this->authorizeResource(Company::class, 'company');
    }

    public function index(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $query = Company::orderBy('name');

        if ($user && ! $user->hasRole('admin')) {
            // Scope to companies explicitly granted + employee's company.
            $explicit = $user->companies()->pluck('companies.id');
            $employeeCompanyId = \App\Models\Employee::query()
                ->where('id', $user->employee_id)
                ->value('company_id');

            $ids = $explicit
                ->when($user->default_company_id, fn ($c) => $c->push($user->default_company_id))
                ->when($employeeCompanyId, fn ($c) => $c->push($employeeCompanyId))
                ->unique()
                ->filter();

            if ($ids->isNotEmpty()) {
                $query->whereKey($ids);
            }
        } elseif ($tenantId = CompanyContext::tenantId()) {
            $query->where('tenant_id', $tenantId);
        }

        return $this->success($query->get());
    }

    public function store(CompanyRequest $request, CompanyPolicyTemplateService $policyTemplates): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();
        $templateCode = $data['policy_template_code'] ?? $data['industry_code'] ?? null;
        unset($data['policy_template_code']);

        if (empty($data['tenant_id'])) {
            $data['tenant_id'] = CompanyContext::tenantId();
        }

        $company = Company::create($data);

        app(CompanyOrgSetupService::class)->ensureDefaultBranch($company);

        if ($templateCode) {
            $policyTemplates->apply($company->id, $templateCode, overwrite: true);
            $company->refresh();
        }

        return $this->success($company, 201);
    }

    public function show(Company $company): \Illuminate\Http\JsonResponse
    {
        return $this->success($company);
    }

    public function update(CompanyRequest $request, Company $company): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();

        $company->update($data);

        return $this->success($company);
    }

    public function destroy(Company $company): \Illuminate\Http\JsonResponse
    {
        $company->delete();

        return $this->noContent();
    }
}
