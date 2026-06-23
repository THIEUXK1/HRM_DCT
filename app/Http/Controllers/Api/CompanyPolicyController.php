<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\UpdateCompanyPolicyDomainRequest;
use App\Services\Company\CompanyPolicyService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyPolicyController extends ApiController
{
    public function __construct(
        private readonly CompanyPolicyService $policies,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorizeView();

        $companyId = (int) CompanyContext::id();

        return $this->success($this->policies->overview($companyId));
    }

    public function showDomain(string $domain): JsonResponse
    {
        $this->authorizeView();

        return $this->success($this->policies->domain((int) CompanyContext::id(), $domain));
    }

    public function updateDomain(UpdateCompanyPolicyDomainRequest $request, string $domain): JsonResponse
    {
        $data = $request->validated();

        return $this->success($this->policies->updateDomain(
            (int) CompanyContext::id(),
            $domain,
            $request->validatedSettings(),
            $data['effective_from'] ?? null,
            auth()->user(),
            $data['notes'] ?? null,
        ));
    }

    public function versions(Request $request): JsonResponse
    {
        $this->authorizeView();

        $companyId = (int) CompanyContext::id();
        $domain = $request->query('domain');

        return $this->success([
            'versions' => app(\App\Services\Company\CompanyPolicyVersionService::class)
                ->list($companyId, is_string($domain) ? $domain : null),
        ]);
    }

    public function groupComparison(): JsonResponse
    {
        $user = auth()->user();
        if (! $user || (! $user->hasRole('admin') && ! $user->can('company_policies.view'))) {
            abort(403);
        }

        return $this->success($this->policies->groupComparison(CompanyContext::tenantId()));
    }

    public function export(): JsonResponse
    {
        $this->authorizeView();

        return $this->success($this->policies->export((int) CompanyContext::id()));
    }

    public function applyToEmployees(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user || (! $user->hasRole('admin') && ! $user->can('company_policies.manage'))) {
            abort(403);
        }

        $data = $request->validate([
            'domain' => ['required', 'string'],
            'settings' => ['required', 'array'],
            'effective_from' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'employee_ids' => ['nullable', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $companyId = (int) CompanyContext::id();
        $employees = \App\Support\EmployeeScopeResolver::resolve(
            $companyId,
            isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            $data['employee_ids'] ?? null,
            isset($data['department_id']) ? (int) $data['department_id'] : null,
        );

        return $this->success($this->policies->applyToEmployees(
            $companyId,
            $employees->pluck('id')->all(),
            $data['domain'],
            $data['settings'],
            $data['effective_from'] ?? null,
            $user,
            $data['notes'] ?? null,
        ));
    }

    public function employeeOverrides(Request $request): JsonResponse
    {
        $this->authorizeView();

        $domain = $request->query('domain');

        return $this->success([
            'rows' => $this->policies->employeesWithOverrides(
                (int) CompanyContext::id(),
                is_string($domain) ? $domain : null,
            ),
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user || (! $user->hasRole('admin') && ! $user->can('company_policies.manage'))) {
            abort(403);
        }

        $payload = $request->validate([
            'settings' => ['required', 'array'],
            'formula_rules' => ['sometimes', 'array'],
        ]);

        return $this->success($this->policies->import(
            (int) CompanyContext::id(),
            $payload,
            $user,
        ));
    }

    private function authorizeView(): void
    {
        $user = auth()->user();
        if (! $user || (! $user->hasRole('admin') && ! $user->can('company_policies.view'))) {
            abort(403);
        }
    }
}
