<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Services\AuditLogger;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmploymentContractBulkService
{
    public function __construct(
        private readonly EmployeeProbationSyncService $probationSync,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{created_count: int, contracts: list<EmploymentContract>, message: string}
     */
    public function createMany(array $data, ?int $companyId = null): array
    {
        $companyId ??= CompanyContext::id();
        $employeeIds = array_values(array_unique(array_map('intval', $data['employee_ids'] ?? [])));
        $prefix = isset($data['contract_number_prefix']) && $data['contract_number_prefix'] !== ''
            ? (string) $data['contract_number_prefix']
            : null;

        unset($data['employee_ids'], $data['contract_number_prefix']);

        $employees = Employee::query()
            ->whereIn('id', $employeeIds)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get()
            ->keyBy('id');

        if ($employees->count() !== count($employeeIds)) {
            throw ValidationException::withMessages([
                'employee_ids' => ['Một hoặc nhiều nhân viên không thuộc công ty đang chọn.'],
            ]);
        }

        $created = [];

        DB::transaction(function () use ($employeeIds, $employees, $data, $prefix, &$created) {
            foreach ($employeeIds as $employeeId) {
                $employee = $employees->get($employeeId);
                if (! $employee) {
                    continue;
                }

                $payload = array_merge($data, [
                    'employee_id' => $employeeId,
                    'contract_number' => $this->generateContractNumber($employee, $prefix),
                ]);

                $contract = EmploymentContract::create($payload);
                $this->probationSync->syncFromContract($contract);

                AuditLogger::log('created', $contract, null, 'contract',
                    "Hợp đồng #{$contract->id} tạo hàng loạt cho NV {$employee->employee_code}");

                $created[] = $contract->load('employee:id,full_name,employee_code');
            }
        });

        $count = count($created);

        return [
            'created_count' => $count,
            'contracts' => $created,
            'message' => "Đã ký {$count} hợp đồng (mỗi nhân viên một HĐ riêng).",
        ];
    }

    private function generateContractNumber(Employee $employee, ?string $prefix): string
    {
        $base = $prefix !== null ? rtrim($prefix, '- ') : 'CTR';
        $code = preg_replace('/\s+/', '', (string) $employee->employee_code) ?: (string) $employee->id;
        $date = now()->format('Ymd');

        $candidate = "{$base}-{$code}-{$date}";
        $suffix = 1;

        while (EmploymentContract::where('contract_number', $candidate)->exists()) {
            $candidate = "{$base}-{$code}-{$date}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
