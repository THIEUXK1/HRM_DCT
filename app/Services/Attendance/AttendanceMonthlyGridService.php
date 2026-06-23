<?php

namespace App\Services\Attendance;

use App\Models\AttendanceSummary;
use App\Models\Employee;
use Carbon\Carbon;

/**
 * Bảng công tháng dạng lưới mẫu Excel — 1 dòng / NV, header 2 tầng TV / CT.
 */
class AttendanceMonthlyGridService
{
    public function __construct(
        private readonly EmploymentPhaseResolver $phaseResolver,
    ) {}

    public function report(int $companyId, string $period, ?int $departmentId = null, ?int $branchId = null): array
    {
        $layout = config('attendance_timesheet_grid');
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $holidays = VietnamHolidayService::forYear($start->year, $companyId);

        $summaries = AttendanceSummary::with(['employee.department:id,name'])
            ->where('company_id', $companyId)
            ->where('period', $period)
            ->when($departmentId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $departmentId)))
            ->when($branchId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $branchId)))
            ->get()
            ->sortBy(fn (AttendanceSummary $s) => $s->employee?->employee_code ?? '');

        $rows = [];
        $stt = 0;

        foreach ($summaries as $summary) {
            $employee = $summary->employee;
            if (! $employee) {
                continue;
            }
            $stt++;
            $rows[] = $this->buildRow($stt, $summary, $employee, $period, $start, $end, $holidays);
        }

        return [
            'period' => $period,
            'title' => $layout['title'] ?? 'BẢNG CÔNG',
            'standard_work_days' => VietnamHolidayService::standardWorkDays($period, false, $companyId),
            'layout' => [
                'info' => $layout['info_columns'] ?? [],
                'standard' => $layout['standard_columns'] ?? [],
                'phases' => $layout['phase_groups'] ?? [],
                'totals' => $layout['total_columns'] ?? [],
            ],
            'summary' => [
                'employee_count' => count($rows),
                'phase_split_count' => collect($rows)->where('has_phase_split', true)->count(),
            ],
            'rows' => $rows,
        ];
    }

    /** @return array<string, mixed> */
    private function buildRow(
        int $stt,
        AttendanceSummary $summary,
        Employee $employee,
        string $period,
        Carbon $start,
        Carbon $end,
        array $holidays,
    ): array {
        $breakdown = is_array($summary->attendance_breakdown) ? $summary->attendance_breakdown : [];
        $meta = $breakdown['meta'] ?? [];
        $otByPhase = $breakdown['ot_by_phase'] ?? [];
        $probationGrid = $otByPhase['probation'] ?? [];
        $officialGrid = $otByPhase['official'] ?? [];

        $hasPhaseSplit = (bool) ($meta['has_phase_split'] ?? false)
            || ((float) $summary->probation_work_days > 0 && (float) $summary->official_work_days > 0);

        $probationEnd = $meta['probation_end_date']
            ?? $this->phaseResolver->probationEndInPeriod($employee, $start, $end)?->format('Y-m-d');

        $probationPaid = (float) $summary->probation_paid_leave_days;
        $officialPaid = (float) $summary->official_paid_leave_days;
        $probationUnpaid = (float) $summary->probation_unpaid_leave_days;
        $officialUnpaid = (float) $summary->official_unpaid_leave_days;

        $probationWork = (float) $summary->probation_work_days;
        $officialWork = (float) $summary->official_work_days;

        if (! $hasPhaseSplit && $probationWork <= 0 && $officialWork <= 0 && (float) $summary->work_days > 0) {
            $officialWork = (float) $summary->work_days;
            $officialPaid = (float) $summary->paid_leave_days;
            $officialUnpaid = (float) $summary->unpaid_leave_days;
        }

        return [
            'stt' => $stt,
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'full_name' => $employee->full_name,
            'department' => $employee->department?->name ?? '',
            'hire_date' => $employee->hire_date?->format('d/m/Y') ?? '',
            'probation_end_date' => $probationEnd ? Carbon::parse($probationEnd)->format('d/m/Y') : '—',
            'has_phase_split' => $hasPhaseSplit,
            'standard_work_days' => (float) $summary->standard_work_days,
            'probation_work_days' => $this->cellNum($probationWork, $hasPhaseSplit || $probationWork > 0),
            'probation_paid_leave' => $this->cellNum($probationPaid, $hasPhaseSplit || $probationPaid > 0),
            'probation_unpaid_leave' => $this->cellNum($probationUnpaid, $hasPhaseSplit || $probationUnpaid > 0),
            'probation_absent' => $this->cellNum(
                $this->absentInPhase($employee, $period, 'probation', $summary, $holidays),
                $hasPhaseSplit,
            ),
            'probation_ot_150' => $this->cellNum($this->otRateHours($probationGrid, '150'), $hasPhaseSplit),
            'probation_ot_200' => $this->cellNum($this->otRateHours($probationGrid, '200'), $hasPhaseSplit),
            'probation_ot_300' => $this->cellNum($this->otRateHours($probationGrid, '300'), $hasPhaseSplit),
            'official_work_days' => $this->cellNum($officialWork, true),
            'official_paid_leave' => $this->cellNum($officialPaid, true),
            'official_unpaid_leave' => $this->cellNum($officialUnpaid, true),
            'official_absent' => $this->cellNum(
                $hasPhaseSplit
                    ? $this->absentInPhase($employee, $period, 'official', $summary, $holidays)
                    : (float) $summary->absent_days,
                true,
            ),
            'official_ot_150' => $this->cellNum($this->otRateHours($officialGrid, '150'), true),
            'official_ot_200' => $this->cellNum($this->otRateHours($officialGrid, '200'), true),
            'official_ot_300' => $this->cellNum($this->otRateHours($officialGrid, '300'), true),
            'work_days' => (float) $summary->work_days,
            'paid_leave_days' => (float) $summary->paid_leave_days,
            'unpaid_leave_days' => (float) $summary->unpaid_leave_days,
            'ot_hours' => (float) $summary->ot_hours,
            'actual_work_hours' => (float) $summary->actual_work_hours,
            'is_locked' => (bool) $summary->is_locked,
            'summary_id' => $summary->id,
            'attendance_breakdown' => $breakdown,
        ];
    }

    /** @param  array<string, float>  $grid */
    private function otRateHours(array $grid, string $rate): float
    {
        return round(match ($rate) {
            '150' => (float) ($grid['day_weekday'] ?? 0) + (float) ($grid['night_weekday'] ?? 0),
            '200' => (float) ($grid['day_weekend'] ?? 0) + (float) ($grid['night_weekend'] ?? 0),
            '300' => (float) ($grid['day_holiday'] ?? 0) + (float) ($grid['night_holiday'] ?? 0),
            default => 0.0,
        }, 2);
    }

    private function absentInPhase(
        Employee $employee,
        string $period,
        string $phase,
        AttendanceSummary $summary,
        array $holidays,
    ): float {
        $phaseDef = collect($this->phaseResolver->phasesInPeriod($employee, $period))
            ->firstWhere('phase', $phase);

        if (! $phaseDef) {
            return 0.0;
        }

        $from = Carbon::parse($phaseDef['from']);
        $to = Carbon::parse($phaseDef['to']);
        $standardInPhase = $this->countStandardWorkDays($from, $to, $holidays);

        if ($phase === 'probation') {
            $work = (float) $summary->probation_work_days;
            $paid = (float) $summary->probation_paid_leave_days;
            $unpaid = (float) $summary->probation_unpaid_leave_days;
        } else {
            $work = (float) $summary->official_work_days;
            $paid = (float) $summary->official_paid_leave_days;
            $unpaid = (float) $summary->official_unpaid_leave_days;
        }

        return max(0.0, round($standardInPhase - $work - $paid - $unpaid, 2));
    }

    private function countStandardWorkDays(Carbon $from, Carbon $to, array $holidays): float
    {
        $count = 0.0;
        $cursor = $from->copy();
        while ($cursor <= $to) {
            if (! $cursor->isSunday() && ! array_key_exists($cursor->format('Y-m-d'), $holidays)) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }

    private function cellNum(float $value, bool $show): float|string
    {
        if (! $show) {
            return '—';
        }

        return $value > 0 ? round($value, 2) : '—';
    }
}
