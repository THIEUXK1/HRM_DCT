<?php

namespace App\Services\Hr;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalHrSyncService
{
    private const API_URL = 'https://bptehr.bestpacific.com/ehr/open/rmt/getVn';

    /**
     * Sync all employees from external EHR.
     * Routes each employee to the correct company based on EMPNO prefix.
     * If $onlyCompanyId is provided, only syncs employees belonging to that company.
     */
    public function sync(?int $onlyCompanyId = null): array
    {
        $employees = $this->fetchExternalData();

        // Build prefix → company map from DB (only companies with a configured prefix)
        $prefixMap = $this->buildPrefixMap($onlyCompanyId);

        if (empty($prefixMap)) {
            return [
                'created' => 0, 'updated' => 0, 'skipped' => 0,
                'errors' => ['Không có công ty nào được cấu hình prefix mã nhân viên.'],
            ];
        }

        // Cache branch per company to avoid N+1
        $branchCache = [];

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        DB::transaction(function () use ($employees, $prefixMap, &$branchCache, &$stats, $onlyCompanyId) {
            foreach ($employees as $row) {
                try {
                    $empNo = trim($row['EMPNO'] ?? '');
                    if (! $empNo) {
                        $stats['skipped']++;
                        continue;
                    }

                    $company = $this->resolveCompany($empNo, $prefixMap);
                    if (! $company) {
                        // Skip silently — employee belongs to a company not in this system (or not configured)
                        $stats['skipped']++;
                        continue;
                    }

                    if (! isset($branchCache[$company->id])) {
                        $branchCache[$company->id] = Branch::where('company_id', $company->id)->first();
                    }
                    $branch = $branchCache[$company->id];

                    $this->syncOne($row, $company->id, $branch?->id, $stats);
                } catch (\Throwable $e) {
                    $empNo = trim($row['EMPNO'] ?? '?');
                    $stats['errors'][] = "EMPNO {$empNo}: {$e->getMessage()}";
                    Log::error('ExternalHrSync error', ['empno' => $empNo, 'error' => $e->getMessage()]);
                }
            }
        });

        return $stats;
    }

    private function fetchExternalData(): array
    {
        $response = Http::timeout(30)->get(self::API_URL);

        if (! $response->successful()) {
            throw new \RuntimeException('Không thể kết nối tới API EHR cũ: HTTP ' . $response->status());
        }

        $body = $response->json();

        if (($body['code'] ?? -1) !== 0) {
            throw new \RuntimeException('API EHR cũ trả về lỗi: code=' . ($body['code'] ?? 'N/A'));
        }

        return $body['rows']['employees'] ?? [];
    }

    /**
     * Build an ordered prefix → Company map.
     * Longer prefixes take priority (e.g. "YP" before "Y").
     */
    private function buildPrefixMap(?int $onlyCompanyId): array
    {
        $query = Company::whereNotNull('employee_code_prefix')->where('is_active', true);
        if ($onlyCompanyId) {
            $query->where('id', $onlyCompanyId);
        }

        $companies = $query->get();

        // Sort longest prefix first so "YP" matches before "Y"
        $sorted = $companies->sortByDesc(fn($c) => strlen($c->employee_code_prefix));

        $map = [];
        foreach ($sorted as $company) {
            $map[strtoupper($company->employee_code_prefix)] = $company;
        }

        return $map;
    }

    /**
     * Find company by matching EMPNO prefix against the map.
     */
    private function resolveCompany(string $empNo, array $prefixMap): ?Company
    {
        $upper = strtoupper($empNo);
        foreach ($prefixMap as $prefix => $company) {
            if (str_starts_with($upper, $prefix)) {
                return $company;
            }
        }
        return null;
    }

    private function syncOne(array $row, int $companyId, ?int $branchId, array &$stats): void
    {
        $empNo = trim($row['EMPNO'] ?? '');

        $department = $this->resolveModel(Department::class, $row['DEPT_CODE'] ?? null, [
            'company_id' => $companyId,
            'branch_id'  => $branchId,
            'name'       => $this->cleanBilingual($row['DEPT_NAME'] ?? ''),
            'is_active'  => true,
        ]);

        $position = $this->resolveModel(Position::class, $row['POSITION_CODE'] ?? null, [
            'department_id' => $department?->id,
            'name'          => $this->cleanBilingual($row['POSITION_NAME'] ?? ''),
            'is_active'     => true,
        ]);

        $status = (int) ($row['STATUS'] ?? 1) === 2 ? 'terminated' : 'active';
        $hireDate = $this->parseDate($row['ENTRY_DATE'] ?? null);
        [$firstName, $lastName] = $this->splitName($row['USER_NAME'] ?? '');
        $fullName = trim($row['USER_NAME'] ?? '');

        $employee = Employee::withTrashed()->where('employee_code', $empNo)->first();

        if ($employee) {
            $updates = ['employment_status' => $status];

            if (! $employee->full_name && $fullName) $updates['full_name'] = $fullName;
            if (! $employee->first_name && $firstName) $updates['first_name'] = $firstName;
            if (! $employee->last_name && $lastName) $updates['last_name'] = $lastName;
            if (! $employee->department_id && $department) $updates['department_id'] = $department->id;
            if (! $employee->position_id && $position) $updates['position_id'] = $position->id;
            if (! $employee->hire_date && $hireDate) $updates['hire_date'] = $hireDate;
            if (! $employee->email) $updates['email'] = "{$empNo}@bestpacific.local";

            $employee->update($updates);
            $stats['updated']++;
        } else {
            Employee::create([
                'company_id'        => $companyId,
                'branch_id'         => $branchId,
                'department_id'     => $department?->id,
                'position_id'       => $position?->id,
                'employee_code'     => $empNo,
                'first_name'        => $firstName,
                'last_name'         => $lastName,
                'full_name'         => $fullName,
                'email'             => "{$empNo}@bestpacific.local",
                'employment_status' => $status,
                'hire_date'         => $hireDate,
                'is_active'         => $status === 'active',
            ]);
            $stats['created']++;
        }
    }

    private function resolveModel(string $model, ?string $code, array $defaults): ?object
    {
        if (! $code) return null;
        return $model::firstOrCreate(['code' => $code], $defaults);
    }

    /** Strip Chinese portion from bilingual strings like "Bộ phận Công trình 工程部" */
    private function cleanBilingual(string $value): string
    {
        $cleaned = preg_replace('/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}]+/u', '', $value);
        return trim($cleaned);
    }

    private function parseDate(?string $value): ?string
    {
        if (! $value) return null;
        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Split "Lê Đình Triển" → ["Lê", "Đình Triển"] */
    private function splitName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);
        return [$parts[0] ?? '', $parts[1] ?? ''];
    }
}
