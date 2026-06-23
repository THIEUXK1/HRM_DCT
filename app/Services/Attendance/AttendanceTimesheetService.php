<?php

namespace App\Services\Attendance;

use App\Models\AttendanceLog;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Models\EmployeeTermination;
use App\Models\EmploymentContract;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Bảng công theo ngày + báo cáo chấm công (OT, chuyên cần, nghỉ phép, thôi việc).
 * Tách giai đoạn thử việc / chính thức theo BLLĐ 2019 Điều 24–27, 26.
 */
class AttendanceTimesheetService
{
    public function __construct(
        private readonly EmploymentPhaseResolver $phaseResolver,
        private readonly LeaveDayCalculator $leaveDayCalculator,
        private readonly DiligenceBonusEvaluator $diligenceEvaluator,
        private readonly AttendanceDisplayConfigService $displayConfig,
    ) {}
    public function dailyTimesheet(int $companyId, string $period, ?int $departmentId = null, ?int $branchId = null): array
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $today = now()->startOfDay();
        $holidays = VietnamHolidayService::forYear($start->year, $companyId);
        $standardDays = VietnamHolidayService::standardWorkDays($period, false, $companyId);
        $shift = WorkShift::where('company_id', $companyId)->where('is_active', true)->orderBy('id')->first();

        $days = $this->buildCalendarDays($start, $end, $holidays);

        $employees = $this->scopedEmployees($companyId, $departmentId, $branchId);
        $employeeIds = $employees->pluck('id');

        $logs = AttendanceLog::whereIn('employee_id', $employeeIds)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('employee_id');

        $leaveMap = $this->buildLeaveDateMap($employeeIds, $start, $end);
        $otMap = $this->buildOtDateMap($employeeIds, $start, $end);
        $terminationMap = $this->terminationEndDates($companyId, $employeeIds);

        $rows = [];
        $stt = 0;
        foreach ($employees as $employee) {
            $stt++;
            $cells = [];
            $totals = [
                'present' => 0,
                'probation_days' => 0,
                'official_days' => 0,
                'leave' => 0,
                'paid_leave' => 0,
                'unpaid_leave' => 0,
                'probation_paid_leave' => 0,
                'official_paid_leave' => 0,
                'probation_unpaid_leave' => 0,
                'official_unpaid_leave' => 0,
                'probation_absent' => 0.0,
                'official_absent' => 0.0,
                'absent' => 0,
                'late' => 0,
                'ot_hours' => 0.0,
                'probation_ot_hours' => 0.0,
                'official_ot_hours' => 0.0,
                // Công theo loại ngày (phase × day type)
                'probation_work_weekday_days' => 0.0,
                'probation_work_weekend_days' => 0.0,
                'probation_work_holiday_days' => 0.0,
                'probation_work_night_weekday_hours' => 0.0,
                'probation_work_night_weekend_hours' => 0.0,
                'probation_work_night_holiday_hours' => 0.0,
                'official_work_weekday_days' => 0.0,
                'official_work_weekend_days' => 0.0,
                'official_work_holiday_days' => 0.0,
                'official_work_night_weekday_hours' => 0.0,
                'official_work_night_weekend_hours' => 0.0,
                'official_work_night_holiday_hours' => 0.0,
                // OT theo loại ngày × ngày/đêm
                'probation_ot_weekday_day' => 0.0,
                'probation_ot_weekday_night' => 0.0,
                'probation_ot_weekend_day' => 0.0,
                'probation_ot_weekend_night' => 0.0,
                'probation_ot_holiday_day' => 0.0,
                'probation_ot_holiday_night' => 0.0,
                'official_ot_weekday_day' => 0.0,
                'official_ot_weekday_night' => 0.0,
                'official_ot_weekend_day' => 0.0,
                'official_ot_weekend_night' => 0.0,
                'official_ot_holiday_day' => 0.0,
                'official_ot_holiday_night' => 0.0,
            ];

            $empLogs = ($logs[$employee->id] ?? collect())->keyBy(fn ($l) => $l->work_date->format('Y-m-d'));
            $termDate = $terminationMap[$employee->id] ?? null;
            $phases = $this->phaseResolver->phasesInPeriod($employee, $period);
            $probationEnd = $this->phaseResolver->probationEndInPeriod($employee, $start, $end);

            foreach ($days as $day) {
                $cells[$day['date']] = $this->buildDayCell(
                    $day,
                    $empLogs[$day['date']] ?? null,
                    $leaveMap[$employee->id][$day['date']] ?? null,
                    $otMap[$employee->id][$day['date']] ?? null,
                    $termDate,
                    $today,
                    $shift,
                    $totals,
                    $this->phaseResolver->phaseOnDate($employee, Carbon::parse($day['date']), $probationEnd),
                );
            }

            foreach ($otMap[$employee->id] ?? [] as $date => $ot) {
                $phase = $this->phaseResolver->phaseOnDate($employee, Carbon::parse($date), $probationEnd) ?? 'official';
                $hours = (float) $ot['hours'];
                $nightH = (float) ($ot['night_hours'] ?? 0);
                $dayH = round(max(0.0, $hours - $nightH), 2);
                $type = match ($ot['ot_type'] ?? 'weekday') {
                    'weekend' => 'weekend',
                    'holiday' => 'holiday',
                    default   => 'weekday',
                };
                $prefix = $phase === 'probation' ? 'probation' : 'official';
                $totals['ot_hours'] += $hours;
                $totals["{$prefix}_ot_hours"] += $hours;
                $totals["{$prefix}_ot_{$type}_day"] += $dayH;
                $totals["{$prefix}_ot_{$type}_night"] += $nightH;
            }
            $totals['probation_ot_150'] = $totals['probation_ot_weekday_day'];
            $totals['official_ot_150'] = $totals['official_ot_weekday_day'];

            $roundKeys = [
                'probation_ot_150', 'official_ot_150',
                'ot_hours', 'probation_ot_hours', 'official_ot_hours',
                'probation_work_weekday_days', 'probation_work_weekend_days', 'probation_work_holiday_days',
                'probation_work_night_weekday_hours', 'probation_work_night_weekend_hours', 'probation_work_night_holiday_hours',
                'official_work_weekday_days', 'official_work_weekend_days', 'official_work_holiday_days',
                'official_work_night_weekday_hours', 'official_work_night_weekend_hours', 'official_work_night_holiday_hours',
                'probation_ot_weekday_day', 'probation_ot_weekday_night',
                'probation_ot_weekend_day', 'probation_ot_weekend_night',
                'probation_ot_holiday_day', 'probation_ot_holiday_night',
                'official_ot_weekday_day', 'official_ot_weekday_night',
                'official_ot_weekend_day', 'official_ot_weekend_night',
                'official_ot_holiday_day', 'official_ot_holiday_night',
            ];
            foreach ($roundKeys as $k) {
                $totals[$k] = round($totals[$k], 2);
            }

            $leaveStats = $this->leaveDayCalculator->summarizeForEmployee(
                $employee,
                $start,
                $end,
                $holidays,
                $probationEnd,
            );
            $totals['leave'] = round($leaveStats['total_leave_days'], 2);
            $totals['paid_leave'] = round($leaveStats['paid_leave_days'], 2);
            $totals['unpaid_leave'] = round($leaveStats['unpaid_leave_days'], 2);
            $totals['probation_paid_leave'] = round($leaveStats['probation_paid_leave_days'], 2);
            $totals['official_paid_leave'] = round($leaveStats['official_paid_leave_days'], 2);
            $totals['probation_unpaid_leave'] = round($leaveStats['probation_unpaid_leave_days'], 2);
            $totals['official_unpaid_leave'] = round($leaveStats['official_unpaid_leave_days'], 2);

            $hasPhaseSplit = count($phases) > 1;
            if ($hasPhaseSplit) {
                $totals['probation_absent'] = $this->absentInPhase($employee, $period, 'probation', $totals, $holidays);
                $totals['official_absent'] = $this->absentInPhase($employee, $period, 'official', $totals, $holidays);
            } else {
                $totals['official_absent'] = (float) $totals['absent'];
            }

            $rows[] = [
                'stt' => $stt,
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'full_name' => $employee->full_name,
                'department' => $employee->department?->name,
                'hire_date' => $employee->hire_date?->format('d/m/Y') ?? '',
                'probation_end_date' => $probationEnd?->format('d/m/Y') ?? '—',
                'probation_end_date_raw' => $probationEnd?->format('Y-m-d'),
                'official_start_date' => $probationEnd ? $probationEnd->copy()->addDay()->format('Y-m-d') : null,
                'has_phase_split' => $hasPhaseSplit,
                'phases' => $phases,
                'cells' => $cells,
                'totals' => $totals,
            ];
        }

        $gridConfig = config('attendance_timesheet_grid');

        return [
            'period' => $period,
            'title' => $gridConfig['daily']['title'] ?? 'BẢNG CÔNG THEO NGÀY',
            'standard_work_days' => $standardDays,
            'days' => $days,
            'layout' => [
                'info' => $gridConfig['daily']['info_columns'] ?? [],
                'summary' => $gridConfig['daily']['summary_columns'] ?? [],
                'phases' => $gridConfig['phase_groups'] ?? [],
            ],
            'summary' => [
                'employee_count' => count($rows),
                'phase_split_count' => collect($rows)->where('has_phase_split', true)->count(),
            ],
            'legal_note' => 'Tách công TV/CT khi NV chuyển giai đoạn thử việc → chính thức trong tháng.',
            'display_config' => $this->displayConfig->forCompany($companyId),
            'employees' => $rows,
        ];
    }

    /**
     * @param  array<string, float|int>  $totals
     */
    private function absentInPhase(Employee $employee, string $period, string $phase, array $totals, array $holidays): float
    {
        $phaseDef = collect($this->phaseResolver->phasesInPeriod($employee, $period))
            ->firstWhere('phase', $phase);

        if (! $phaseDef) {
            return 0.0;
        }

        $from = Carbon::parse($phaseDef['from']);
        $to = Carbon::parse($phaseDef['to']);
        $standardInPhase = $this->countStandardWorkDays($from, $to, $holidays);

        if ($phase === 'probation') {
            $work = (float) $totals['probation_days'];
            $paid = (float) $totals['probation_paid_leave'];
            $unpaid = (float) $totals['probation_unpaid_leave'];
        } else {
            $work = (float) $totals['official_days'];
            $paid = (float) $totals['official_paid_leave'];
            $unpaid = (float) $totals['official_unpaid_leave'];
        }

        return max(0.0, round($standardInPhase - $work - $paid - $unpaid, 2));
    }

    /**
     * Bảng công tháng tách theo giai đoạn (mỗi NV 1–2 dòng: trước/sau thử việc).
     * Chuẩn thực tế phần mềm lương VN (AMIS, MISA…).
     */
    public function phasedMonthlyReport(int $companyId, string $period, ?int $departmentId = null, ?int $branchId = null): array
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $holidays = VietnamHolidayService::forYear($start->year, $companyId);

        $employees = $this->scopedEmployees($companyId, $departmentId, $branchId);

        // Batch-load active contracts để tính tỷ lệ lương thử việc
        $contracts = EmploymentContract::whereIn('employee_id', $employees->pluck('id'))
            ->where('status', 'active')
            ->orderByDesc('start_date')
            ->get()
            ->keyBy('employee_id');

        $rows = [];
        $stt = 0;
        $lastEmployeeId = null;

        foreach ($employees as $employee) {
            $phases = $this->phaseResolver->phasesInPeriod($employee, $period);
            $probationEnd = $this->phaseResolver->probationEndInPeriod($employee, $start, $end);
            $hasPhaseSplit = count($phases) > 1;

            // Tính tỷ lệ lương thử việc từ hợp đồng thực tế
            $contract = $contracts->get($employee->id);
            $baseSalary = $contract ? (float) $contract->salary_base : 0.0;
            $probationSalary = $contract ? (float) ($contract->probation_salary ?: $baseSalary) : $baseSalary;
            $probationRatePct = $baseSalary > 0 ? (int) round($probationSalary / $baseSalary * 100) : 100;

            if ($lastEmployeeId !== $employee->id) {
                $stt++;
                $lastEmployeeId = $employee->id;
            }

            foreach ($phases as $phaseDef) {
                $phaseStart = Carbon::parse($phaseDef['from']);
                $phaseEnd = Carbon::parse($phaseDef['to']);

                $workDays = AttendanceLog::where('employee_id', $employee->id)
                    ->whereBetween('work_date', [$phaseStart->toDateString(), $phaseEnd->toDateString()])
                    ->whereNotNull('check_in_at')
                    ->count();

                $standardInPhase = $this->countStandardWorkDays($phaseStart, $phaseEnd, $holidays);

                $leaveStats = $this->leaveDayCalculator->summarizeForEmployee(
                    $employee,
                    $phaseStart,
                    $phaseEnd,
                    $holidays,
                    $probationEnd,
                );
                $leaveDays = $leaveStats['total_leave_days'];
                $paidLeaveDays = $phaseDef['phase'] === 'probation'
                    ? $leaveStats['probation_paid_leave_days']
                    : $leaveStats['official_paid_leave_days'];
                $unpaidLeaveDays = $phaseDef['phase'] === 'probation'
                    ? $leaveStats['probation_unpaid_leave_days']
                    : $leaveStats['official_unpaid_leave_days'];

                $otBreakdown   = $this->otHoursInRange($employee->id, $phaseStart, $phaseEnd);
                $workBreakdown = $this->workDayTypeBreakdown($employee->id, $phaseStart, $phaseEnd);
                $absentDays    = max(0, $standardInPhase - $workDays - $leaveDays);

                $rows[] = [
                    'stt' => $stt,
                    'employee_id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'full_name' => $employee->full_name,
                    'department' => $employee->department?->name,
                    'phase' => $phaseDef['phase'],
                    'phase_label' => $phaseDef['label'],
                    'from_date' => $phaseDef['from'],
                    'to_date' => $phaseDef['to'],
                    'date_range' => Carbon::parse($phaseDef['from'])->format('d/m').' → '.Carbon::parse($phaseDef['to'])->format('d/m'),
                    'salary_rate' => $phaseDef['salary_rate'],
                    'salary_rate_label' => $phaseDef['phase'] === 'probation'
                        ? ($probationRatePct >= 100 ? '100%' : "{$probationRatePct}%")
                        : '100%',
                    'standard_work_days' => $standardInPhase,
                    'work_days' => $workDays,
                    'work_weekday_days' => $workBreakdown['weekday_days'],
                    'work_weekend_days' => $workBreakdown['weekend_days'],
                    'work_holiday_days' => $workBreakdown['holiday_days'],
                    'work_night_weekday_hours' => $workBreakdown['work_night_weekday_hours'],
                    'work_night_weekend_hours' => $workBreakdown['work_night_weekend_hours'],
                    'work_night_holiday_hours' => $workBreakdown['work_night_holiday_hours'],
                    'leave_days' => $leaveDays,
                    'paid_leave_days' => round($paidLeaveDays, 2),
                    'unpaid_leave_days' => round($unpaidLeaveDays, 2),
                    'absent_days' => round($absentDays, 2),
                    'ot_weekday_day'   => $otBreakdown['weekday_day'],
                    'ot_weekday_night' => $otBreakdown['weekday_night'],
                    'ot_weekend_day'   => $otBreakdown['weekend_day'],
                    'ot_weekend_night' => $otBreakdown['weekend_night'],
                    'ot_holiday_day'   => $otBreakdown['holiday_day'],
                    'ot_holiday_night' => $otBreakdown['holiday_night'],
                    'ot_hours' => $otBreakdown['total'],
                    'ot_150' => $otBreakdown['weekday_day'],
                    'ot_200' => $otBreakdown['weekend_day'],
                    'ot_300' => $otBreakdown['holiday_day'],
                    'payable_work_days' => round($workDays + $paidLeaveDays, 2),
                    'has_phase_split' => $hasPhaseSplit,
                    'probation_end_date' => $probationEnd?->format('d/m/Y') ?? '—',
                    'probation_end_date_raw' => $probationEnd?->format('Y-m-d'),
                ];
            }
        }

        $gridConfig = config('attendance_timesheet_grid.phased', []);

        return [
            'period' => $period,
            'title' => $gridConfig['title'] ?? 'BẢNG CÔNG TV / CT GIAI ĐOẠN',
            'standard_work_days' => VietnamHolidayService::standardWorkDays($period, false, $companyId),
            'layout' => [
                'info' => $gridConfig['info_columns'] ?? [],
                'metrics' => $gridConfig['metric_columns'] ?? [],
            ],
            'summary' => [
                'total_lines' => count($rows),
                'probation_lines' => collect($rows)->where('phase', 'probation')->count(),
                'official_lines' => collect($rows)->where('phase', 'official')->count(),
                'split_employees' => collect($rows)->groupBy('employee_id')->filter(fn ($g) => $g->count() > 1)->count(),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Công phân loại ngày trong khoảng thời gian (dùng cho phased report).
     *
     * @return array{
     *   weekday_days: float, weekend_days: float, holiday_days: float,
     *   work_night_weekday_hours: float, work_night_weekend_hours: float, work_night_holiday_hours: float
     * }
     */
    private function workDayTypeBreakdown(int $employeeId, Carbon $from, Carbon $to): array
    {
        $result = [
            'weekday_days' => 0.0, 'weekend_days' => 0.0, 'holiday_days' => 0.0,
            'work_night_weekday_hours' => 0.0,
            'work_night_weekend_hours' => 0.0,
            'work_night_holiday_hours' => 0.0,
        ];

        $logs = AttendanceLog::where('employee_id', $employeeId)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('check_in_at')
            ->get(['is_holiday', 'is_weekend', 'night_hours']);

        foreach ($logs as $log) {
            $nightH = (float) ($log->night_hours ?? 0);
            if ($log->is_holiday) {
                $result['holiday_days']++;
                $result['work_night_holiday_hours'] += $nightH;
            } elseif ($log->is_weekend) {
                $result['weekend_days']++;
                $result['work_night_weekend_hours'] += $nightH;
            } else {
                $result['weekday_days']++;
                $result['work_night_weekday_hours'] += $nightH;
            }
        }

        foreach ($result as $k => $v) {
            $result[$k] = round($v, 2);
        }

        return $result;
    }

    /**
     * @return array{
     *   weekday_day: float, weekday_night: float,
     *   weekend_day: float, weekend_night: float,
     *   holiday_day: float, holiday_night: float,
     *   total: float
     * }
     */
    private function otHoursInRange(int $employeeId, Carbon $from, Carbon $to): array
    {
        $breakdown = [
            'weekday_day' => 0.0, 'weekday_night' => 0.0,
            'weekend_day' => 0.0, 'weekend_night' => 0.0,
            'holiday_day' => 0.0, 'holiday_night' => 0.0,
            'total' => 0.0,
        ];

        $requests = OvertimeRequest::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        foreach ($requests as $ot) {
            $hours = (float) $ot->hours;
            $nightH = (float) ($ot->night_hours ?? 0);
            $dayH = round(max(0.0, $hours - $nightH), 2);
            $type = match ($ot->ot_type ?? 'weekday') {
                'weekend' => 'weekend',
                'holiday' => 'holiday',
                default   => 'weekday',
            };
            $breakdown["{$type}_day"] += $dayH;
            $breakdown["{$type}_night"] += $nightH;
            $breakdown['total'] += $hours;
        }

        foreach ($breakdown as $key => $value) {
            $breakdown[$key] = round($value, 2);
        }

        return $breakdown;
    }

    public function overtimeReport(int $companyId, string $period, ?int $departmentId = null, ?int $branchId = null): array
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $employees = $this->scopedEmployees($companyId, $departmentId, $branchId);
        $summaries = AttendanceSummary::where('company_id', $companyId)
            ->where('period', $period)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        $otDetails = OvertimeRequest::with('employee:id,full_name,employee_code,department_id', 'employee.department:id,name')
            ->where('company_id', $companyId)
            ->where('status', 'approved')
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->when($departmentId || $branchId, fn ($q) => $this->applyEmployeeRelationScope($q, $departmentId, $branchId))
            ->orderBy('work_date')
            ->get();

        $rows = [];
        foreach ($employees as $employee) {
            $summary = $summaries[$employee->id] ?? null;
            $empOt = $otDetails->where('employee_id', $employee->id);
            if (! $summary && $empOt->isEmpty()) {
                continue;
            }

            $probationOt = 0.0;
            $officialOt = 0.0;
            foreach ($empOt as $ot) {
                $phase = $this->phaseResolver->phaseOnDate($employee, Carbon::parse($ot->work_date));
                if ($phase === 'probation') {
                    $probationOt += (float) $ot->hours;
                } else {
                    $officialOt += (float) $ot->hours;
                }
            }

            $rows[] = [
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'full_name' => $employee->full_name,
                'department' => $employee->department?->name,
                'ot_weekday_hours' => (float) ($summary?->ot_weekday_hours ?? 0),
                'ot_weekend_hours' => (float) ($summary?->ot_weekend_hours ?? 0),
                'ot_holiday_hours' => (float) ($summary?->ot_holiday_hours ?? 0),
                'ot_total_hours' => (float) ($summary?->ot_hours ?? $empOt->sum('hours')),
                'ot_probation_hours' => round($probationOt, 2),
                'ot_official_hours' => round($officialOt, 2),
                'night_hours' => (float) ($summary?->night_hours ?? 0),
                'ot_monthly_cap_exceeded' => (bool) ($summary?->ot_monthly_cap_exceeded ?? false),
                'ot_grid' => $summary?->attendance_breakdown['ot'] ?? [],
                'leave_by_type' => $summary?->attendance_breakdown['leave_by_type'] ?? [],
                'work_meta' => $summary?->attendance_breakdown['work'] ?? [],
                'requests' => $empOt->map(fn ($r) => [
                    'work_date' => $r->work_date->format('Y-m-d'),
                    'hours' => (float) $r->hours,
                    'ot_type' => $r->ot_type ?? 'weekday',
                    'employment_phase' => $this->phaseResolver->phaseOnDate($employee, Carbon::parse($r->work_date)),
                    'reason' => $r->reason,
                ])->values()->all(),
            ];
        }

        return [
            'period' => $period,
            'summary' => [
                'total_employees' => count($rows),
                'total_ot_hours' => round(collect($rows)->sum('ot_total_hours'), 2),
                'cap_exceeded_count' => collect($rows)->where('ot_monthly_cap_exceeded', true)->count(),
            ],
            'rows' => $rows,
        ];
    }

    public function diligenceReport(int $companyId, string $period, ?int $departmentId = null, ?int $branchId = null): array
    {
        $summaries = AttendanceSummary::with(['employee:id,full_name,employee_code,department_id', 'employee.department:id,name'])
            ->where('company_id', $companyId)
            ->where('period', $period)
            ->when($departmentId || $branchId, fn ($q) => $this->applyEmployeeRelationScope($q, $departmentId, $branchId))
            ->orderBy('employee_id')
            ->get();

        $rows = $summaries->map(function (AttendanceSummary $s) {
            $standard = max(1, (float) $s->standard_work_days);
            $attendanceRate = round(((float) $s->work_days / $standard) * 100, 1);
            $evaluation = $this->diligenceEvaluator->evaluate($s, $attendanceRate);

            return [
                'employee_id' => $s->employee_id,
                'employee_code' => $s->employee?->employee_code,
                'full_name' => $s->employee?->full_name,
                'department' => $s->employee?->department?->name,
                'standard_work_days' => (float) $s->standard_work_days,
                'work_days' => (float) $s->work_days,
                'absent_days' => (float) $s->absent_days,
                'leave_days' => (float) $s->leave_days,
                'late_count' => (int) $s->late_count,
                'late_minutes' => (float) $s->late_minutes,
                'early_count' => (int) $s->early_count,
                'forgot_punch_count' => (int) ($s->forgot_punch_count ?? 0),
                'correction_approved_count' => (int) ($s->correction_approved_count ?? 0),
                'attendance_rate' => $attendanceRate,
                'diligence_grade' => $evaluation['grade'],
                'diligence_bonus_eligible' => $evaluation['eligible'],
                'diligence_bonus_amount' => $evaluation['bonus_amount'],
                'diligence_disqualify_reasons' => $evaluation['disqualify_reasons'],
            ];
        })->values()->all();

        $eligibleCount = collect($rows)->where('diligence_bonus_eligible', true)->count();

        return [
            'period' => $period,
            'summary' => [
                'total_employees' => count($rows),
                'avg_attendance_rate' => count($rows)
                    ? round(collect($rows)->avg('attendance_rate'), 1)
                    : 0,
                'total_absent_days' => round(collect($rows)->sum('absent_days'), 2),
                'total_late_incidents' => collect($rows)->sum('late_count'),
                'bonus_eligible_count' => $eligibleCount,
                'total_bonus_amount' => round(collect($rows)->sum('diligence_bonus_amount'), 0),
            ],
            'rows' => $rows,
        ];
    }

    public function leaveReport(int $companyId, string $period, ?int $departmentId = null, ?int $branchId = null): array
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $requests = LeaveRequest::with([
            'employee:id,full_name,employee_code,department_id',
            'employee.department:id,name',
            'leaveType:id,code,name,is_paid,cell_symbol,legal_reference',
        ])
            ->where('company_id', $companyId)
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->when($departmentId || $branchId, fn ($q) => $this->applyEmployeeRelationScope($q, $departmentId, $branchId))
            ->orderByDesc('start_date')
            ->get();

        $rows = $requests->map(fn (LeaveRequest $r) => [
            'id' => $r->id,
            'employee_code' => $r->employee?->employee_code,
            'full_name' => $r->employee?->full_name,
            'department' => $r->employee?->department?->name,
            'leave_type' => $r->leaveType?->name,
            'leave_code' => $r->leaveType?->code,
            'cell_symbol' => $r->leaveType?->cell_symbol,
            'is_paid' => (bool) ($r->leaveType?->is_paid ?? true),
            'paid_label' => ($r->leaveType?->is_paid ?? true) ? 'Có hưởng lương' : 'Không hưởng lương',
            'legal_reference' => $r->leaveType?->legal_reference,
            'start_date' => $r->start_date->format('Y-m-d'),
            'end_date' => $r->end_date->format('Y-m-d'),
            'total_days' => (float) $r->total_days,
            'status' => $r->status,
            'reason' => $r->reason,
        ])->values()->all();

        $approved = collect($rows)->where('status', 'approved');
        $approvedPaid = $approved->where('is_paid', true);
        $approvedUnpaid = $approved->where('is_paid', false);

        return [
            'period' => $period,
            'summary' => [
                'total_requests' => count($rows),
                'approved_requests' => $approved->count(),
                'approved_days' => round($approved->sum('total_days'), 2),
                'approved_paid_days' => round($approvedPaid->sum('total_days'), 2),
                'approved_unpaid_days' => round($approvedUnpaid->sum('total_days'), 2),
                'pending_requests' => collect($rows)->where('status', 'pending')->count(),
            ],
            'rows' => $rows,
        ];
    }

    public function terminationReport(int $companyId, string $period, ?int $departmentId = null, ?int $branchId = null): array
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $terminations = EmployeeTermination::with([
            'employee:id,full_name,employee_code,department_id,hire_date',
            'employee.department:id,name',
        ])
            ->where('company_id', $companyId)
            ->whereBetween('termination_date', [$start->toDateString(), $end->toDateString()])
            ->when($departmentId || $branchId, fn ($q) => $this->applyEmployeeRelationScope($q, $departmentId, $branchId))
            ->orderByDesc('termination_date')
            ->get();

        $rows = [];
        foreach ($terminations as $term) {
            $termDate = Carbon::parse($term->termination_date);
            $monthStart = $start->copy();
            $workDaysInMonth = AttendanceLog::where('employee_id', $term->employee_id)
                ->whereBetween('work_date', [$monthStart->toDateString(), $termDate->toDateString()])
                ->whereNotNull('check_in_at')
                ->count();

            $rows[] = [
                'employee_id' => $term->employee_id,
                'employee_code' => $term->employee?->employee_code,
                'full_name' => $term->employee?->full_name,
                'department' => $term->employee?->department?->name,
                'termination_date' => $termDate->format('Y-m-d'),
                'type' => $term->type,
                'reason_type' => $term->reason_type,
                'reason' => $term->reason,
                'status' => $term->status,
                'work_days_before_exit' => $workDaysInMonth,
                'final_settlement_done' => (bool) $term->final_settlement_done,
            ];
        }

        return [
            'period' => $period,
            'summary' => [
                'total_terminations' => count($rows),
                'approved' => collect($rows)->where('status', 'approved')->count(),
                'completed' => collect($rows)->where('status', 'completed')->count(),
            ],
            'rows' => $rows,
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function scopedEmployees(int $companyId, ?int $departmentId, ?int $branchId = null): Collection
    {
        return Employee::with('department:id,name')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->orderBy('employee_code')
            ->get(['id', 'employee_code', 'full_name', 'department_id', 'hire_date', 'probation_end_date', 'official_start_date']);
    }

    private function applyEmployeeRelationScope($query, ?int $departmentId, ?int $branchId, string $relation = 'employee'): void
    {
        $query->whereHas($relation, function ($employee) use ($departmentId, $branchId) {
            if ($departmentId) {
                $employee->where('department_id', $departmentId);
            }
            if ($branchId) {
                $employee->where('branch_id', $branchId);
            }
        });
    }

    /** @return array<int, string> employee_id => termination_date */
    private function terminationEndDates(int $companyId, Collection $employeeIds): array
    {
        return EmployeeTermination::where('company_id', $companyId)
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('status', ['approved', 'completed'])
            ->pluck('termination_date', 'employee_id')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->all();
    }

    private function buildCalendarDays(Carbon $start, Carbon $end, array $holidays): array
    {
        $days = [];
        $current = $start->copy();
        while ($current <= $end) {
            $date = $current->format('Y-m-d');
            $isHoliday = array_key_exists($date, $holidays);
            $isSunday = $current->isSunday();
            $isSaturday = $current->isSaturday();

            $days[] = [
                'date' => $date,
                'day' => (int) $current->format('j'),
                'weekday' => (int) $current->format('N'),
                'weekday_label' => $current->locale('vi')->isoFormat('dd'),
                'is_weekend' => $isSunday,
                'is_saturday' => $isSaturday,
                'is_holiday' => $isHoliday,
                'is_workday' => ! $isSunday && ! $isHoliday,
                'holiday_name' => $holidays[$date] ?? null,
            ];
            $current->addDay();
        }

        return $days;
    }

    private function buildLeaveDateMap(Collection $employeeIds, Carbon $start, Carbon $end): array
    {
        $requests = LeaveRequest::with('leaveType:id,code,name,is_paid,cell_symbol,day_count_mode')
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get();

        $map = [];
        foreach ($requests as $req) {
            $cursor = Carbon::parse($req->start_date)->max($start);
            $reqEnd = Carbon::parse($req->end_date)->min($end);
            while ($cursor <= $reqEnd) {
                $map[$req->employee_id][$cursor->format('Y-m-d')] = [
                    'code' => $req->leaveType?->code ?? 'PHEP',
                    'name' => $req->leaveType?->name ?? 'Nghỉ phép',
                    'is_paid' => (bool) ($req->leaveType?->is_paid ?? true),
                    'day_count_mode' => $req->leaveType?->day_count_mode ?? 'workday',
                    'cell_symbol' => $req->leaveType?->cell_symbol
                        ?? (($req->leaveType?->is_paid ?? true) ? 'P' : 'KL'),
                ];
                $cursor->addDay();
            }
        }

        return $map;
    }

    /** @param array<string, mixed> $leave */
    private function renderLeaveCell(array $leave, array &$totals, ?string $employmentPhase = null): array
    {
        $totals['leave']++;
        if ($leave['is_paid']) {
            $totals['paid_leave']++;
            if ($employmentPhase === 'probation') {
                $totals['probation_paid_leave']++;
            } elseif ($employmentPhase === 'official') {
                $totals['official_paid_leave']++;
            }
        } else {
            $totals['unpaid_leave']++;
            if ($employmentPhase === 'probation') {
                $totals['probation_unpaid_leave']++;
            } elseif ($employmentPhase === 'official') {
                $totals['official_unpaid_leave']++;
            }
        }

        $symbol = $leave['cell_symbol'] ?? ($leave['is_paid'] ? 'P' : 'KL');
        $status = $leave['is_paid'] ? 'paid_leave' : 'unpaid_leave';
        $label = $leave['name'].($leave['is_paid'] ? ' · có lương' : ' · không lương');

        return $this->cell($symbol, $status, $label, null, $leave['code']);
    }

    private function buildOtDateMap(Collection $employeeIds, Carbon $start, Carbon $end): array
    {
        $requests = OvertimeRequest::whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $map = [];
        foreach ($requests as $ot) {
            $date = $ot->work_date->format('Y-m-d');
            $map[$ot->employee_id][$date] = [
                'hours' => (float) $ot->hours,
                'ot_type' => $ot->ot_type ?? 'weekday',
                'night_hours' => (float) ($ot->night_hours ?? 0),
            ];
        }

        return $map;
    }

    private function buildDayCell(
        array $day,
        ?AttendanceLog $log,
        ?array $leave,
        ?array $ot,
        ?string $termDate,
        Carbon $today,
        ?WorkShift $shift,
        array &$totals,
        ?string $employmentPhase = null,
    ): array {
        $date = $day['date'];
        $cellDate = Carbon::parse($date);

        if ($termDate && $date > $termDate) {
            return $this->cell('NV', 'terminated', 'Đã nghỉ việc', $ot);
        }

        if ($cellDate->gt($today)) {
            return $this->cell('—', 'future', 'Chưa đến', null);
        }

        if ($leave && ($leave['day_count_mode'] ?? 'workday') === 'calendar') {
            return $this->renderLeaveCell($leave, $totals, $employmentPhase);
        }

        if ($day['is_holiday']) {
            return $this->cell('L', 'holiday', $day['holiday_name'] ?? 'Ngày lễ', null);
        }

        if ($day['is_weekend']) {
            return $this->cell('CN', 'weekend', 'Chủ nhật', null);
        }

        if ($leave) {
            return $this->renderLeaveCell($leave, $totals, $employmentPhase);
        }

        if ($log && $log->check_in_at) {
            $lateMinutes = $this->lateMinutesForLog($log, $shift);
            $isLate = $lateMinutes > 0;
            if ($isLate) {
                $totals['late']++;
            }
            $totals['present']++;
            if ($employmentPhase === 'probation') {
                $totals['probation_days']++;
            } elseif ($employmentPhase === 'official') {
                $totals['official_days']++;
            }
            // Công phân loại ngày (phase × day type)
            $prefix = $employmentPhase === 'probation' ? 'probation' : 'official';
            $nightH = (float) ($log->night_hours ?? 0);
            if ($day['is_holiday']) {
                $totals["{$prefix}_work_holiday_days"]++;
                $totals["{$prefix}_work_night_holiday_hours"] += $nightH;
            } elseif ($day['is_weekend']) {
                $totals["{$prefix}_work_weekend_days"]++;
                $totals["{$prefix}_work_night_weekend_hours"] += $nightH;
            } else {
                $totals["{$prefix}_work_weekday_days"]++;
                $totals["{$prefix}_work_night_weekday_hours"] += $nightH;
            }

            $symbol = $isLate ? 'T' : 'X';
            $phaseLabel = $employmentPhase === 'probation' ? 'Công thử việc' : 'Công chính thức';

            return $this->cell(
                $symbol,
                $isLate ? 'late' : 'present',
                $phaseLabel.' · '.$this->checkTimeLabel($log),
                $ot,
                null,
                round((float) ($log->work_hours ?: 0), 1),
                $lateMinutes,
                $employmentPhase,
            );
        }

        if ($day['is_workday']) {
            $totals['absent']++;

            return $this->cell('V', 'absent', 'Vắng không phép', null);
        }

        return $this->cell('—', 'off', 'Không tính công', null);
    }

    private function cell(
        string $symbol,
        string $status,
        string $label,
        ?array $ot = null,
        ?string $leaveCode = null,
        ?float $workHours = null,
        ?float $lateMinutes = null,
        ?string $employmentPhase = null,
    ): array {
        return array_filter([
            'symbol' => $symbol,
            'status' => $status,
            'label' => $label,
            'employment_phase' => $employmentPhase,
            'leave_code' => $leaveCode,
            'work_hours' => $workHours,
            'late_minutes' => $lateMinutes,
            'ot_hours' => $ot['hours'] ?? null,
            'ot_type' => $ot['ot_type'] ?? null,
        ], fn ($v) => $v !== null);
    }

    private function countStandardWorkDays(Carbon $from, Carbon $to, array $holidays): int
    {
        $count = 0;
        $current = $from->copy();
        while ($current <= $to) {
            $isSunday = $current->isSunday();
            $isHoliday = array_key_exists($current->format('Y-m-d'), $holidays);
            if (! $isSunday && ! $isHoliday) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }

    private function lateMinutesForLog(AttendanceLog $log, ?WorkShift $shift): float
    {
        if ($log->late_minutes > 0) {
            return (float) $log->late_minutes;
        }

        if (! $shift || ! $log->check_in_at) {
            return 0;
        }

        $workDate = Carbon::parse($log->work_date);
        $shiftStart = Carbon::parse($workDate->format('Y-m-d').' '.$shift->start_time);
        $checkIn = Carbon::parse($log->check_in_at);

        return $checkIn->gt($shiftStart) ? round(abs($checkIn->diffInMinutes($shiftStart)), 2) : 0;
    }

    private function checkTimeLabel(AttendanceLog $log): string
    {
        $in = $log->check_in_at ? Carbon::parse($log->check_in_at)->format('H:i') : '—';
        $out = $log->check_out_at ? Carbon::parse($log->check_out_at)->format('H:i') : '—';

        return "{$in} – {$out}";
    }
}
