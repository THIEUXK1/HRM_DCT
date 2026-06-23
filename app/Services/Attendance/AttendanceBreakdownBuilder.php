<?php

namespace App\Services\Attendance;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Xây attendance_breakdown JSON — Phase 2a (BestPacific sheet công).
 */
class AttendanceBreakdownBuilder
{
    public function __construct(
        private readonly AttendanceOtGridCalculator $otGridCalculator,
        private readonly LeaveDayCalculator $leaveDayCalculator,
        private readonly EmploymentPhaseResolver $phaseResolver,
        private readonly LeaveDurationCalculator $leaveDurationCalculator,
    ) {}

    /**
     * @param  array<string, float>  $leaveByType
     * @return array<string, mixed>
     */
    public function build(
        Employee $employee,
        Carbon $start,
        Carbon $end,
        string $period,
        array $holidays,
        int $standardDays,
        Collection $otRequests,
        array $leaveByType,
        float $workDays,
        float $paidLeaveDays,
        float $absentDays,
        float $probationWorkDays,
        float $officialWorkDays,
        ?Carbon $probationEndDate,
        array $leaveStats = [],
        ?array $leaveByTypeByPhase = null,
        float $workNightWeekdayHours = 0.0,
        float $workNightWeekendHours = 0.0,
        float $workNightHolidayHours = 0.0,
    ): array {
        $otGrid = $this->otGridCalculator->calculate($employee, $otRequests, $start, $end, $holidays);
        $otByPhase = $this->otGridCalculator->calculateByPhase(
            $employee,
            $otRequests,
            $start,
            $end,
            $holidays,
            $probationEndDate,
        );
        $leaveByTypeByPhase ??= $this->leaveDayCalculator->summarizeByLeaveTypeByPhase(
            $employee,
            $start,
            $end,
            $holidays,
            $probationEndDate,
        );

        $holidayDaysInMonth = $this->countHolidaysInPeriod($start, $end, $holidays);
        $daysNotJoined = $this->daysNotJoined($employee, $start, $end, $holidays);
        $businessTripDays = (float) ($leaveByType['business_trip'] ?? 0);
        $menstrualHours = round((float) ($leaveByType['menstrual'] ?? 0) * 8, 2);
        $saturdayDutyHours = $this->saturdayDutyHours($employee, $start, $end);

        $employmentStatus = $officialWorkDays > 0 && $probationWorkDays <= 0
            ? 'official'
            : ($probationWorkDays > 0 && $officialWorkDays <= 0 ? 'probation' : 'mixed');

        $joinDateInPeriod = null;
        if ($employee->hire_date) {
            $hire = Carbon::parse($employee->hire_date);
            if ($hire->betweenIncluded($start, $end)) {
                $joinDateInPeriod = $hire;
            }
        }

        $leaveForPayslip = $leaveByType;
        $leaveForPayslip['unauthorized'] = round($absentDays, 2);

        unset($leaveForPayslip['business_trip'], $leaveForPayslip['menstrual'], $leaveForPayslip['unpaid'], $leaveForPayslip['compensatory']);

        return [
            'version' => 1,
            'period' => $period,
            'ot' => $otGrid,
            'ot_by_phase' => $otByPhase,
            'leave_by_type' => $leaveForPayslip,
            'leave_by_phase' => [
                'probation' => [
                    'paid' => round((float) ($leaveStats['probation_paid_leave_days'] ?? 0), 2),
                    'unpaid' => round((float) ($leaveStats['probation_unpaid_leave_days'] ?? 0), 2),
                    'by_type' => $leaveByTypeByPhase['probation'] ?? [],
                ],
                'official' => [
                    'paid' => round((float) ($leaveStats['official_paid_leave_days'] ?? 0), 2),
                    'unpaid' => round((float) ($leaveStats['official_unpaid_leave_days'] ?? 0), 2),
                    'by_type' => $leaveByTypeByPhase['official'] ?? [],
                ],
            ],
            'work' => [
                'payable_work_days' => round($workDays, 2),
                'probation_work_days' => round($probationWorkDays, 2),
                'official_work_days' => round($officialWorkDays, 2),
                'minimum_wage_days' => 0.0,
                'base_salary_paid_leave_days' => round($paidLeaveDays, 2),
                'holiday_days' => $holidayDaysInMonth,
                'business_trip_days' => $businessTripDays,
                'menstrual_leave_hours' => $menstrualHours,
                'days_not_joined' => $daysNotJoined,
                'join_date' => $joinDateInPeriod?->format('Y-m-d'),
                'saturday_duty_hours' => $saturdayDutyHours,
                'travel_support' => null,
                // NC_D/NC_N theo mã công chuẩn (BLLĐ 2019 Điều 106):
                // NC_D = giờ làm ban ngày bình thường (100%A, nằm trong lương tháng)
                // NC_N = giờ làm ban đêm bình thường (130%A = 100% + 30% phụ trội)
                'nc_day_hours' => round(max(0.0, $workDays * 8 - $workNightWeekdayHours), 2),
                'nc_night_weekday_hours' => round($workNightWeekdayHours, 2),
                'nc_night_weekend_hours' => round($workNightWeekendHours, 2),
                'nc_night_holiday_hours' => round($workNightHolidayHours, 2),
                'nc_night_total_hours' => round($workNightWeekdayHours + $workNightWeekendHours + $workNightHolidayHours, 2),
            ],
            'meta' => [
                'employment_status' => $employmentStatus,
                'employment_status_raw' => config("cong_luong_sheet.import_status_codes.{$employmentStatus}", '正式'),
                'employment_status_label' => config("cong_luong_sheet.employment_status_labels.{$employmentStatus}", 'Chính thức'),
                'employment_active_label' => $employee->is_active
                    ? config('cong_luong_sheet.employment_active_labels.active')
                    : config('cong_luong_sheet.employment_active_labels.inactive'),
                'has_phase_split' => $employmentStatus === 'mixed',
                'probation_end_date' => $probationEndDate?->format('Y-m-d'),
                'hire_date' => $employee->hire_date?->format('Y-m-d'),
                'join_date_in_period' => $joinDateInPeriod?->format('Y-m-d'),
                'standard_work_days' => $standardDays,
            ],
        ];
    }

    /**
     * @return array<string, float>
     */
    public function emptyLeaveByType(): array
    {
        $keys = array_unique(array_values(config('attendance_vn.leave_type_breakdown_map', [])));
        $keys[] = 'unauthorized';

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = 0.0;
        }

        return $result;
    }

    private function countHolidaysInPeriod(Carbon $start, Carbon $end, array $holidays): float
    {
        $count = 0;
        $cursor = $start->copy();
        while ($cursor <= $end) {
            if (array_key_exists($cursor->format('Y-m-d'), $holidays)) {
                $count++;
            }
            $cursor->addDay();
        }

        return (float) $count;
    }

    private function daysNotJoined(Employee $employee, Carbon $start, Carbon $end, array $holidays): float
    {
        if (! $employee->hire_date) {
            return 0.0;
        }

        $hire = Carbon::parse($employee->hire_date)->startOfDay();
        if ($hire->lte($start) || $hire->gt($end)) {
            return 0.0;
        }

        $days = 0.0;
        $cursor = $start->copy();
        while ($cursor->lt($hire)) {
            if ($this->leaveDurationCalculator->isWorkday($cursor, $holidays)) {
                $days++;
            }
            $cursor->addDay();
        }

        return $days;
    }

    private function saturdayDutyHours(Employee $employee, Carbon $start, Carbon $end): float
    {
        return round((float) AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('check_in_at')
            ->get()
            ->filter(fn ($log) => Carbon::parse($log->work_date)->isSaturday())
            ->sum('work_hours'), 2);
    }
}
