<?php

namespace App\Services\Payroll;

use App\Models\AttendanceLog;
use App\Models\AttendanceSummary;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeReview;
use App\Models\EmployeeTermination;
use App\Models\EmploymentContract;
use App\Models\PerformanceCycle;
use App\Services\Company\CompanyPolicyResolver;
use Carbon\Carbon;

class PayrollContextBuilder
{
    public function build(
        Employee $employee,
        EmploymentContract $contract,
        AttendanceSummary $summary,
        string $period,
        array $earned,
    ): array {
        $breakdown = $earned['breakdown'];
        $standardDays = max(1.0, (float) $summary->standard_work_days);
        $baseSalary = (float) ($breakdown['base_salary_monthly'] ?? $contract->salary_base);

        $termination = $this->terminationContext($employee, $period, $standardDays, $baseSalary);
        $performance = $this->performanceContext($employee, $period, (int) $employee->company_id);

        return array_merge($breakdown, $termination, $performance, [
            'daily_salary' => round($baseSalary / $standardDays, 0),
            'gross_before_formulas' => (float) $earned['gross_salary'],
        ]);
    }

    /** @return array<string, mixed> */
    private function terminationContext(
        Employee $employee,
        string $period,
        float $standardDays,
        float $baseSalary,
    ): array {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $termination = EmployeeTermination::where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'completed'])
            ->whereBetween('termination_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('termination_date')
            ->first();

        if (! $termination) {
            return [
                'is_terminated_in_month' => 0,
                'termination_date' => '',
                'work_days_until_exit' => 0,
                'payable_days_until_exit' => 0,
                'unused_leave_days' => 0,
            ];
        }

        $termDate = Carbon::parse($termination->termination_date);
        $workDaysUntilExit = AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $termDate->toDateString()])
            ->whereNotNull('check_in_at')
            ->count();

        $unusedLeave = CompanyPolicyResolver::for($employee->company_id, $period, $employee->id)
            ->getFloat('termination_unused_leave_days_default', 0);

        return [
            'is_terminated_in_month' => 1,
            'termination_date' => $termDate->format('Y-m-d'),
            'work_days_until_exit' => (float) $workDaysUntilExit,
            'payable_days_until_exit' => (float) $workDaysUntilExit,
            'unused_leave_days' => $unusedLeave,
            'daily_salary' => round($baseSalary / max(1, $standardDays), 0),
        ];
    }

    /** @return array<string, mixed> */
    private function performanceContext(Employee $employee, string $period, int $companyId): array
    {
        $policy = CompanyPolicyResolver::for($companyId, $period, $employee->id);
        $enabled = $policy->getBool('performance_bonus_enabled', true);
        $bonusRate = $policy->getFloat('performance_bonus_rate', 0.15);

        $company = Company::find($companyId);
        $cycle = $company?->tenant_id
            ? PerformanceCycle::where('tenant_id', $company->tenant_id)
                ->where('period', $period)
                ->whereIn('status', ['active', 'closed'])
                ->orderByDesc('id')
                ->first()
            : null;

        $review = $cycle
            ? EmployeeReview::where('employee_id', $employee->id)
                ->where('performance_cycle_id', $cycle->id)
                ->where('status', 'completed')
                ->whereNotNull('final_score')
                ->first()
            : null;

        $score = $enabled && $review ? (float) $review->final_score : 0.0;

        return [
            'performance_score' => $score,
            'performance_rating' => $review?->rating ?? '',
            'performance_bonus_rate' => $bonusRate,
            'has_performance_score' => $score > 0 ? 1 : 0,
            'performance_cycle_name' => $cycle?->name ?? '',
        ];
    }
}
