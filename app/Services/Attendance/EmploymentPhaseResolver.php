<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Services\Hr\EmployeeProbationSyncService;
use Carbon\Carbon;

/**
 * Xác định giai đoạn hợp đồng (thử việc / chính thức) theo BLLĐ 2019 Điều 24–27.
 */
class EmploymentPhaseResolver
{
        public function __construct(
        private readonly EmployeeProbationSyncService $probationSync,
    ) {}

    public function probationEndInPeriod(Employee|int $employee, Carbon $periodStart, Carbon $periodEnd): ?Carbon
    {
        $employeeModel = $employee instanceof Employee ? $employee : Employee::find($employee);
        if (! $employeeModel) {
            return null;
        }

        return $this->probationSync->probationEndInPeriod(
            $employeeModel,
            $periodStart->copy()->startOfDay(),
            $periodEnd->copy()->endOfDay(),
        );
    }

    public function phaseOnDate(Employee|int $employee, Carbon $date, ?Carbon $probationEnd = null): ?string
    {
        $employeeModel = $employee instanceof Employee ? $employee : Employee::find($employee);
        if (! $employeeModel) {
            return null;
        }

        if ($employeeModel->hire_date && $date->lt(Carbon::parse($employeeModel->hire_date))) {
            return null;
        }

        $probationEnd ??= $this->probationSync->resolveProbationEnd($employeeModel);
        if ($probationEnd === null) {
            return 'official';
        }

        return $date->lte($probationEnd) ? 'probation' : 'official';
    }

    /**
     * @return array<int, array{phase: string, label: string, from: string, to: string, salary_rate: float}>
     */
    public function phasesInPeriod(Employee|int $employee, string $period): array
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $employeeModel = $employee instanceof Employee ? $employee : Employee::find($employee);

        $hire = $employeeModel?->hire_date ? Carbon::parse($employeeModel->hire_date) : null;
        $rangeStart = $hire && $hire->gt($start) ? $hire->copy() : $start->copy();
        $rangeEnd = $end->copy();

        if ($rangeStart->gt($rangeEnd)) {
            return [];
        }

        $probationEnd = $this->probationEndInPeriod($employee, $start, $end);

        if ($probationEnd === null) {
            return [[
                'phase' => 'official',
                'label' => 'Chính thức',
                'from' => $rangeStart->toDateString(),
                'to' => $rangeEnd->toDateString(),
                'salary_rate' => 1.0,
            ]];
        }

        $officialStart = $probationEnd->copy()->addDay();

        if ($probationEnd->lt($rangeStart)) {
            return [[
                'phase' => 'official',
                'label' => 'Chính thức',
                'from' => $rangeStart->toDateString(),
                'to' => $rangeEnd->toDateString(),
                'salary_rate' => 1.0,
            ]];
        }

        if ($officialStart->gt($rangeEnd)) {
            return [[
                'phase' => 'probation',
                'label' => 'Thử việc',
                'from' => $rangeStart->toDateString(),
                'to' => $probationEnd->min($rangeEnd)->toDateString(),
                'salary_rate' => 1.0,
            ]];
        }

        return [[
            'phase' => 'probation',
            'label' => 'Thử việc (trước)',
            'from' => $rangeStart->toDateString(),
            'to' => $probationEnd->toDateString(),
            'salary_rate' => 1.0,
        ], [
            'phase' => 'official',
            'label' => 'Chính thức (sau)',
            'from' => $officialStart->toDateString(),
            'to' => $rangeEnd->toDateString(),
            'salary_rate' => 1.0,
        ]];
    }
}
