<?php

namespace App\Services\Company;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\EmployeePolicySetting;
use App\Models\PayrollFormulaRule;
use App\Models\User;
use App\Support\EmployeeScopeResolver;
use App\Models\WorkScheduleGroup;
use App\Services\AuditLogger;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CompanyPolicyService
{
    public function __construct(
        private readonly CompanyPolicyVersionService $versionService,
        private readonly CompanyPolicyTemplateService $templateService,
    ) {}

    /** @return array<string, mixed> */
    public function overview(int $companyId): array
    {
        $company = Company::findOrFail($companyId);
        $resolver = CompanyPolicyResolver::for($companyId);

        $domains = [];
        foreach (config('company_policy_domains.domains', []) as $code => $def) {
            $domains[$code] = [
                'label' => $def['label'],
                'settings' => $this->formatDomainSettings($code, $resolver->domain($code)),
            ];
        }

        $template = null;
        if ($company->policy_template_code) {
            $template = $this->templateService->findTemplate($company->policy_template_code);
        }

        return [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'code' => $company->code,
                'industry_code' => $company->industry_code,
                'policy_template_code' => $company->policy_template_code,
                'policy_applied_at' => optional($company->policy_applied_at)->toIso8601String(),
            ],
            'template' => $template,
            'domains' => $domains,
            'work_schedule_summary' => [
                'groups' => WorkScheduleGroup::where('company_id', $companyId)->count(),
            ],
            'formula_rules_count' => PayrollFormulaRule::where('company_id', $companyId)->where('is_active', true)->count(),
            'recent_versions' => $this->versionService->list($companyId, null, 10),
        ];
    }

    /** @return array<string, mixed> */
    public function domain(int $companyId, string $domain): array
    {
        $this->assertValidDomain($domain);
        $resolver = CompanyPolicyResolver::for($companyId);

        return [
            'domain' => $domain,
            'label' => config("company_policy_domains.domains.{$domain}.label"),
            'settings' => $this->formatDomainSettings($domain, $resolver->domain($domain)),
            'versions' => $this->versionService->list($companyId, $domain, 20),
        ];
    }

    /**
     * @param  array<string, string>  $settings
     * @return array<string, mixed>
     */
    public function updateDomain(
        int $companyId,
        string $domain,
        array $settings,
        ?string $effectiveFrom = null,
        ?User $user = null,
        ?string $notes = null,
    ): array {
        $this->assertValidDomain($domain);
        $allowedKeys = config("company_policy_domains.domains.{$domain}.keys", []);
        $filtered = array_intersect_key($settings, array_flip($allowedKeys));

        if ($filtered === []) {
            throw new RuntimeException('Không có thiết lập hợp lệ để lưu.');
        }

        $effectiveFrom = $effectiveFrom
            ?? Carbon::now()->startOfMonth()->toDateString();

        return DB::transaction(function () use ($companyId, $domain, $filtered, $effectiveFrom, $user, $notes) {
            $now = now();
            foreach ($filtered as $key => $value) {
                CompanySetting::updateOrCreate(
                    ['company_id' => $companyId, 'key' => $key],
                    ['value' => (string) $value],
                );
            }

            CompanyPolicyResolver::flushCache();

            $version = $this->versionService->record(
                $companyId,
                $domain,
                $filtered,
                $effectiveFrom,
                $user,
                $notes,
            );

            AuditLogger::log(
                'company_policy_updated',
                Company::find($companyId),
                null,
                'organization',
                "Cập nhật chính sách «{$domain}» từ {$effectiveFrom}",
                null,
                ['domain' => $domain, 'keys' => array_keys($filtered)],
            );

            return [
                'domain' => $domain,
                'settings' => $this->formatDomainSettings($domain, $filtered),
                'version_id' => $version->id,
                'effective_from' => $effectiveFrom,
                'updated_at' => $now->toIso8601String(),
            ];
        });
    }

    /**
     * Áp dụng chính sách cho một hoặc nhiều nhân viên (ghi đè theo NV).
     *
     * @param  array<int>  $employeeIds
     * @param  array<string, string>  $settings
     * @return array<string, mixed>
     */
    public function applyToEmployees(
        int $companyId,
        array $employeeIds,
        string $domain,
        array $settings,
        ?string $effectiveFrom = null,
        ?User $user = null,
        ?string $notes = null,
    ): array {
        $this->assertValidDomain($domain);
        $allowedKeys = config("company_policy_domains.domains.{$domain}.keys", []);
        $filtered = array_intersect_key($settings, array_flip($allowedKeys));

        if ($filtered === []) {
            throw new RuntimeException('Không có thiết lập hợp lệ để áp dụng.');
        }

        $employees = EmployeeScopeResolver::resolve($companyId, null, $employeeIds, null);
        $effectiveFrom = $effectiveFrom
            ?? Carbon::now()->startOfMonth()->toDateString();

        return DB::transaction(function () use ($companyId, $employees, $domain, $filtered, $effectiveFrom, $user, $notes) {
            $applied = 0;
            foreach ($employees as $employee) {
                foreach ($filtered as $key => $value) {
                    EmployeePolicySetting::updateOrCreate(
                        [
                            'company_id' => $companyId,
                            'employee_id' => $employee->id,
                            'key' => $key,
                            'effective_from' => $effectiveFrom,
                        ],
                        [
                            'domain' => $domain,
                            'value' => (string) $value,
                            'applied_by' => $user?->id,
                            'notes' => $notes,
                        ],
                    );
                }
                $applied++;
            }

            CompanyPolicyResolver::flushCache();

            AuditLogger::log(
                'company_policy_applied_employees',
                Company::find($companyId),
                null,
                'organization',
                "Áp dụng chính sách «{$domain}» cho {$applied} nhân viên từ {$effectiveFrom}",
                null,
                [
                    'domain' => $domain,
                    'employee_ids' => $employees->pluck('id')->all(),
                    'keys' => array_keys($filtered),
                ],
            );

            return [
                'domain' => $domain,
                'applied_count' => $applied,
                'employee_ids' => $employees->pluck('id')->values()->all(),
                'settings' => $this->formatDomainSettings($domain, $filtered),
                'effective_from' => $effectiveFrom,
            ];
        });
    }

    /** @return list<array<string, mixed>> */
    public function employeesWithOverrides(int $companyId, ?string $domain = null): array
    {
        $query = EmployeePolicySetting::query()
            ->where('company_id', $companyId)
            ->with('employee:id,employee_code,full_name,department_id', 'employee.department:id,name');

        if ($domain) {
            $query->where('domain', $domain);
        }

        return $query->orderByDesc('effective_from')
            ->limit(200)
            ->get()
            ->map(fn (EmployeePolicySetting $row) => [
                'id' => $row->id,
                'employee_id' => $row->employee_id,
                'employee_code' => $row->employee?->employee_code,
                'full_name' => $row->employee?->full_name,
                'department' => $row->employee?->department?->name,
                'domain' => $row->domain,
                'key' => $row->key,
                'value' => $row->value,
                'effective_from' => $row->effective_from?->format('Y-m-d'),
                'notes' => $row->notes,
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    public function export(int $companyId): array
    {
        $company = Company::findOrFail($companyId);
        $resolver = CompanyPolicyResolver::for($companyId);

        return [
            'exported_at' => now()->toIso8601String(),
            'company_code' => $company->code,
            'policy_template_code' => $company->policy_template_code,
            'settings' => $resolver->allManagedSettings(),
            'formula_rules' => PayrollFormulaRule::where('company_id', $companyId)
                ->get(['code', 'name', 'target_field', 'formula', 'apply_when', 'is_active', 'sort_order'])
                ->toArray(),
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function import(int $companyId, array $payload, ?User $user = null): array
    {
        $settings = $payload['settings'] ?? [];
        if (! is_array($settings) || $settings === []) {
            throw new RuntimeException('File import không có settings.');
        }

        $byDomain = [];
        foreach (config('company_policy_domains.domains', []) as $domain => $def) {
            foreach ($def['keys'] ?? [] as $key) {
                if (array_key_exists($key, $settings)) {
                    $byDomain[$domain][$key] = (string) $settings[$key];
                }
            }
        }

        $updated = 0;
        foreach ($byDomain as $domain => $domainSettings) {
            $this->updateDomain($companyId, $domain, $domainSettings, null, $user, 'Import JSON');
            $updated += count($domainSettings);
        }

        return ['imported_keys' => $updated, 'domains' => array_keys($byDomain)];
    }

    /** So sánh chính sách các CTTV trong tenant (Phase 4). */
    public function groupComparison(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? CompanyContext::tenantId();

        $companies = Company::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'industry_code', 'policy_template_code']);

        $compareKeys = [
            'standard_working_days',
            'annual_leave_standard',
            'attendance_geofence_strict',
            'compliance_alerts_enabled',
            'performance_bonus_enabled',
            'sales_commission_enabled',
        ];

        $rows = [];
        foreach ($companies as $company) {
            $resolver = CompanyPolicyResolver::for($company->id);
            $policy = [];
            foreach ($compareKeys as $key) {
                $policy[$key] = $resolver->getString($key);
            }
            $template = $company->policy_template_code
                ? config("company_policy_templates.{$company->policy_template_code}.name")
                : null;

            $rows[] = [
                'company_id' => $company->id,
                'company_name' => $company->name,
                'company_code' => $company->code,
                'industry_code' => $company->industry_code,
                'policy_template_code' => $company->policy_template_code,
                'policy_template_name' => $template,
                'policy' => $policy,
            ];
        }

        return [
            'tenant_id' => $tenantId,
            'compare_keys' => $compareKeys,
            'labels' => config('company_policy_domains.labels', []),
            'companies' => $rows,
        ];
    }

    /** @param  array<string, string>  $settings */
    private function formatDomainSettings(string $domain, array $settings): array
    {
        $labels = config('company_policy_domains.labels', []);
        $out = [];
        foreach ($settings as $key => $value) {
            $out[] = [
                'key' => $key,
                'label' => $labels[$key] ?? $key,
                'value' => $value,
            ];
        }

        return $out;
    }

    private function assertValidDomain(string $domain): void
    {
        if (! config("company_policy_domains.domains.{$domain}")) {
            throw new RuntimeException("Miền chính sách «{$domain}» không hợp lệ.");
        }
    }
}
