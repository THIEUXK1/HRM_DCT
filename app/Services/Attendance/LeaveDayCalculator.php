<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\Carbon;

/**
 * Đếm ngày nghỉ theo phân loại lương VN: có lương công ty / không lương / BHXH.
 */
class LeaveDayCalculator
{
    public function __construct(
        private readonly EmploymentPhaseResolver $phaseResolver,
        private readonly LeaveDurationCalculator $durationCalculator,
    ) {}

    /**
     * @return array{
     *   total_leave_days: float,
     *   paid_leave_days: float,
     *   unpaid_leave_days: float,
     *   bhxh_leave_days: float,
     *   probation_paid_leave_days: float,
     *   official_paid_leave_days: float,
     *   probation_unpaid_leave_days: float,
     *   official_unpaid_leave_days: float,
     *   probation_bhxh_leave_days: float,
     *   official_bhxh_leave_days: float
     * }
     */
    public function summarizeForEmployee(
        Employee|int $employee,
        Carbon $start,
        Carbon $end,
        array $holidays,
        ?Carbon $probationEndDate = null,
    ): array {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        $requests = LeaveRequest::with('leaveType:id,is_paid,payroll_category,cell_symbol,code,day_count_mode')
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get();

        $paid = 0.0;
        $unpaid = 0.0;
        $bhxh = 0.0;
        $probationPaid = 0.0;
        $officialPaid = 0.0;
        $probationUnpaid = 0.0;
        $officialUnpaid = 0.0;
        $probationBhxh = 0.0;
        $officialBhxh = 0.0;

        foreach ($requests as $req) {
            $cursor = Carbon::parse($req->start_date)->max($start);
            $reqEnd = Carbon::parse($req->end_date)->min($end);
            $category = LeaveType::resolvePayrollCategory($req->leaveType);
            $calendarMode = $this->durationCalculator->countsOnCalendarDays($req->leaveType ?? 'workday');

            while ($cursor <= $reqEnd) {
                $countable = $calendarMode
                    ? true
                    : $this->durationCalculator->isWorkday($cursor, $holidays);

                if ($countable) {
                    $phase = $this->phaseResolver->phaseOnDate($employee, $cursor, $probationEndDate) ?? 'official';

                    match ($category) {
                        'company_paid' => (function () use ($phase, &$paid, &$probationPaid, &$officialPaid) {
                            $paid++;
                            $phase === 'probation' ? $probationPaid++ : $officialPaid++;
                        })(),
                        'bhxh_benefit' => (function () use ($phase, &$bhxh, &$probationBhxh, &$officialBhxh) {
                            $bhxh++;
                            $phase === 'probation' ? $probationBhxh++ : $officialBhxh++;
                        })(),
                        default => (function () use ($phase, &$unpaid, &$probationUnpaid, &$officialUnpaid) {
                            $unpaid++;
                            $phase === 'probation' ? $probationUnpaid++ : $officialUnpaid++;
                        })(),
                    };
                }
                $cursor->addDay();
            }
        }

        return [
            'total_leave_days' => $paid + $unpaid + $bhxh,
            'paid_leave_days' => $paid,
            'unpaid_leave_days' => $unpaid,
            'bhxh_leave_days' => $bhxh,
            'probation_paid_leave_days' => $probationPaid,
            'official_paid_leave_days' => $officialPaid,
            'probation_unpaid_leave_days' => $probationUnpaid,
            'official_unpaid_leave_days' => $officialUnpaid,
            'probation_bhxh_leave_days' => $probationBhxh,
            'official_bhxh_leave_days' => $officialBhxh,
        ];
    }

    /**
     * @return array<string, float>
     */
    public function summarizeByLeaveType(
        Employee|int $employee,
        Carbon $start,
        Carbon $end,
        array $holidays,
        ?Carbon $probationEndDate = null,
    ): array {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;
        $map = config('attendance_vn.leave_type_breakdown_map', []);
        $byType = [];
        foreach (array_unique(array_values($map)) as $key) {
            $byType[$key] = 0.0;
        }

        $requests = LeaveRequest::with('leaveType:id,is_paid,payroll_category,code,day_count_mode')
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get();

        foreach ($requests as $req) {
            $code = $req->leaveType?->code;
            $breakdownKey = $code ? ($map[$code] ?? null) : null;
            if (! $breakdownKey) {
                continue;
            }

            $cursor = Carbon::parse($req->start_date)->max($start);
            $reqEnd = Carbon::parse($req->end_date)->min($end);
            $calendarMode = $this->durationCalculator->countsOnCalendarDays($req->leaveType ?? 'workday');

            while ($cursor <= $reqEnd) {
                $countable = $calendarMode
                    ? true
                    : $this->durationCalculator->isWorkday($cursor, $holidays);

                if ($countable) {
                    $byType[$breakdownKey] = ($byType[$breakdownKey] ?? 0) + 1;
                }
                $cursor->addDay();
            }
        }

        foreach ($byType as $key => $value) {
            $byType[$key] = round((float) $value, 2);
        }

        return $byType;
    }

    /**
     * @return array{probation: array<string, float>, official: array<string, float>}
     */
    public function summarizeByLeaveTypeByPhase(
        Employee|int $employee,
        Carbon $start,
        Carbon $end,
        array $holidays,
        ?Carbon $probationEndDate = null,
    ): array {
        $map = config('attendance_vn.leave_type_breakdown_map', []);
        $probation = [];
        $official = [];
        foreach (array_unique(array_values($map)) as $key) {
            $probation[$key] = 0.0;
            $official[$key] = 0.0;
        }

        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        $requests = LeaveRequest::with('leaveType:id,is_paid,payroll_category,code,day_count_mode')
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get();

        foreach ($requests as $req) {
            $code = $req->leaveType?->code;
            $breakdownKey = $code ? ($map[$code] ?? null) : null;
            if (! $breakdownKey) {
                continue;
            }

            $cursor = Carbon::parse($req->start_date)->max($start);
            $reqEnd = Carbon::parse($req->end_date)->min($end);
            $calendarMode = $this->durationCalculator->countsOnCalendarDays($req->leaveType ?? 'workday');

            while ($cursor <= $reqEnd) {
                $countable = $calendarMode
                    ? true
                    : $this->durationCalculator->isWorkday($cursor, $holidays);

                if ($countable) {
                    $phase = $this->phaseResolver->phaseOnDate($employee, $cursor, $probationEndDate) ?? 'official';
                    if ($phase === 'probation') {
                        $probation[$breakdownKey] = ($probation[$breakdownKey] ?? 0) + 1;
                    } else {
                        $official[$breakdownKey] = ($official[$breakdownKey] ?? 0) + 1;
                    }
                }
                $cursor->addDay();
            }
        }

        foreach ($probation as $key => $value) {
            $probation[$key] = round((float) $value, 2);
        }
        foreach ($official as $key => $value) {
            $official[$key] = round((float) $value, 2);
        }

        return [
            'probation' => $probation,
            'official' => $official,
        ];
    }
}
