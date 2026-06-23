<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmploymentContract;
use Carbon\Carbon;

/**
 * Đồng bộ ngày hết thử việc / ngày chính thức từ HĐ lao động và hồ sơ NV.
 */
class EmployeeProbationSyncService
{
    public function activeProbationContract(Employee $employee): ?EmploymentContract
    {
        return EmploymentContract::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->where('probation_months', '>', 0)
            ->orderByDesc('start_date')
            ->first();
    }

    public function effectiveProbationStart(Employee $employee, EmploymentContract $contract): Carbon
    {
        $contractStart = Carbon::parse($contract->start_date);
        $hire = $employee->hire_date ? Carbon::parse($employee->hire_date) : null;

        if ($hire && $hire->gt($contractStart)) {
            return $hire->copy()->startOfDay();
        }

        return $contractStart->copy()->startOfDay();
    }

    public function probationEndFromContract(Employee $employee, EmploymentContract $contract): ?Carbon
    {
        if ((int) ($contract->probation_months ?? 0) <= 0) {
            return null;
        }

        $start = $this->effectiveProbationStart($employee, $contract);

        return $start->copy()->addMonths((int) $contract->probation_months)->subDay()->startOfDay();
    }

    /**
     * Ngày hết thử việc — ưu tiên field trên NV, fallback HĐ active.
     */
    public function resolveProbationEnd(Employee $employee, ?EmploymentContract $contract = null): ?Carbon
    {
        if ($employee->probation_end_date) {
            return Carbon::parse($employee->probation_end_date)->startOfDay();
        }

        $contract ??= $this->activeProbationContract($employee);

        return $contract ? $this->probationEndFromContract($employee, $contract) : null;
    }

    /**
     * Ngày hết TV có ý nghĩa trong kỳ tháng (null nếu đã hết TV trước kỳ).
     */
    public function probationEndInPeriod(Employee $employee, Carbon $periodStart, Carbon $periodEnd): ?Carbon
    {
        $probationEnd = $this->resolveProbationEnd($employee);
        if ($probationEnd === null) {
            return null;
        }

        if ($probationEnd->lt($periodStart->copy()->startOfDay())) {
            return null;
        }

        return $probationEnd;
    }

    public function syncFromContract(EmploymentContract $contract): void
    {
        $employee = Employee::withoutGlobalScopes()->find($contract->employee_id);
        if (! $employee || $contract->status !== 'active') {
            return;
        }

        $updates = array_filter([
            'insurance_salary' => $contract->insurance_salary ?: null,
            'work_location' => $contract->work_location ?: null,
        ], fn ($v) => $v !== null && $v !== '');

        $probationEnd = $this->probationEndFromContract($employee, $contract);
        if ($probationEnd) {
            if (! $employee->probation_end_date) {
                $updates['probation_end_date'] = $probationEnd->toDateString();
                $updates['official_start_date'] = $probationEnd->copy()->addDay()->toDateString();
            }
            $updates['employment_status'] = $this->employmentStatusForDate(
                $employee,
                $this->resolveProbationEnd($employee->fresh()),
                now(),
            );
        } elseif ((int) ($contract->probation_months ?? 0) === 0) {
            $updates['employment_status'] = 'active';
        }

        if ($contract->start_date && ! $employee->hire_date) {
            $updates['hire_date'] = Carbon::parse($contract->start_date)->toDateString();
        }

        if ($updates !== []) {
            $employee->update($updates);
        }
    }

    public function refreshEmploymentStatus(Employee $employee, ?Carbon $asOf = null): void
    {
        $asOf ??= now();
        $probationEnd = $this->resolveProbationEnd($employee);
        $status = $this->employmentStatusForDate($employee, $probationEnd, $asOf);

        if ($employee->employment_status !== $status) {
            $employee->update(['employment_status' => $status]);
        }
    }

    private function employmentStatusForDate(Employee $employee, ?Carbon $probationEnd, Carbon $asOf): string
    {
        if ($probationEnd === null) {
            return 'active';
        }

        if ($asOf->startOfDay()->lte($probationEnd)) {
            return 'probation';
        }

        return 'active';
    }
}
