<?php

namespace App\Services\Attendance;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\OvertimeExcessRecord;
use App\Models\OvertimeRequest;
use Carbon\Carbon;

/**
 * Cảnh báo tuân thủ: ngày làm liên tục, OT vượt mức, đi làm ngoài lịch.
 */
class WorkScheduleComplianceService
{
    public function __construct(
        private readonly WorkScheduleResolver $resolver,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function scanEmployeePeriod(Employee $employee, string $period): array
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $alerts = [];

        $schedule = $this->resolver->activeSchedule($employee->id, $end);
        $maxConsecutive = (int) ($schedule?->pattern?->max_consecutive_work_days
            ?? config('work_schedule_vn.max_consecutive_work_days', 13));

        $consecutive = $this->longestConsecutiveWorkStreak($employee->id, $start, $end);
        if ($consecutive['days'] > $maxConsecutive) {
            $alerts[] = [
                'type' => 'consecutive_days',
                'severity' => 'warning',
                'message' => sprintf(
                    'NV %s làm liên tục %d ngày (tối đa %d ngày) từ %s đến %s.',
                    $employee->employee_code,
                    $consecutive['days'],
                    $maxConsecutive,
                    $consecutive['from'],
                    $consecutive['to'],
                ),
                'days' => $consecutive['days'],
                'max_allowed' => $maxConsecutive,
                'from_date' => $consecutive['from'],
                'to_date' => $consecutive['to'],
            ];
        }

        if ($schedule?->pattern) {
            $logs = AttendanceLog::where('employee_id', $employee->id)
                ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                ->whereNotNull('check_in_at')
                ->get();

            foreach ($logs as $log) {
                $date = Carbon::parse($log->work_date);
                if (! $this->resolver->isWorkDayForPattern($schedule->pattern, $schedule, $date)) {
                    $alerts[] = [
                        'type' => 'unexpected_work_day',
                        'severity' => 'info',
                        'message' => sprintf(
                            'NV %s đi làm ngày %s không thuộc lịch ca (%s).',
                            $employee->employee_code,
                            $date->format('d/m/Y'),
                            $schedule->pattern->name,
                        ),
                        'work_date' => $date->format('Y-m-d'),
                    ];
                }
            }
        }

        $otSummary = OvertimeCapValidator::summary($employee->id, $period);
        if ($otSummary['monthly_exceeded']) {
            $alerts[] = [
                'type' => 'ot_monthly',
                'severity' => 'warning',
                'message' => sprintf(
                    'OT tháng %s: %.1fh (vượt %.0fh/tháng — NĐ 145/2020).',
                    $period,
                    $otSummary['monthly_used'],
                    $otSummary['monthly_max'],
                ),
                'hours_used' => $otSummary['monthly_used'],
                'hours_max' => $otSummary['monthly_max'],
            ];
        }
        if ($otSummary['yearly_exceeded']) {
            $alerts[] = [
                'type' => 'ot_yearly',
                'severity' => 'warning',
                'message' => sprintf(
                    'OT năm %d: %.1fh (vượt %.0fh/năm — Điều 107 BLLĐ).',
                    Carbon::createFromFormat('Y-m', $period)->year,
                    $otSummary['yearly_used'],
                    $otSummary['yearly_max'],
                ),
                'hours_used' => $otSummary['yearly_used'],
                'hours_max' => $otSummary['yearly_max'],
            ];
        }

        $excessCount = OvertimeExcessRecord::where('employee_id', $employee->id)
            ->where('period', $period)
            ->where('exclude_from_payroll', true)
            ->count();

        if ($excessCount > 0) {
            $excessHours = (float) OvertimeExcessRecord::where('employee_id', $employee->id)
                ->where('period', $period)
                ->sum('excess_hours');

            $alerts[] = [
                'type' => 'ot_excess_payroll',
                'severity' => 'warning',
                'message' => sprintf(
                    'Có %d lần OT vượt mức pháp luật (%.1fh) — tách khỏi bảng lương.',
                    $excessCount,
                    $excessHours,
                ),
                'excess_hours' => $excessHours,
                'record_count' => $excessCount,
            ];
        }

        return $alerts;
    }

    /**
     * @return array{days: int, from: string|null, to: string|null}
     */
    public function longestConsecutiveWorkStreak(int $employeeId, Carbon $start, Carbon $end): array
    {
        $dates = AttendanceLog::where('employee_id', $employeeId)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('check_in_at')
            ->orderBy('work_date')
            ->pluck('work_date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->unique()
            ->values()
            ->all();

        if ($dates === []) {
            return ['days' => 0, 'from' => null, 'to' => null];
        }

        $best = 1;
        $bestFrom = $dates[0];
        $bestTo = $dates[0];
        $current = 1;
        $currentFrom = $dates[0];

        for ($i = 1; $i < count($dates); $i++) {
            $prev = Carbon::parse($dates[$i - 1]);
            $cur = Carbon::parse($dates[$i]);

            if ($prev->copy()->addDay()->isSameDay($cur)) {
                $current++;
            } else {
                if ($current > $best) {
                    $best = $current;
                    $bestFrom = $currentFrom;
                    $bestTo = $dates[$i - 1];
                }
                $current = 1;
                $currentFrom = $dates[$i];
            }
        }

        if ($current > $best) {
            $best = $current;
            $bestFrom = $currentFrom;
            $bestTo = $dates[count($dates) - 1];
        }

        return ['days' => $best, 'from' => $bestFrom, 'to' => $bestTo];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCompanyAlerts(int $companyId, string $period): array
    {
        $employees = Employee::where('company_id', $companyId)
            ->where('is_active', true)
            ->get(['id', 'employee_code', 'full_name']);

        $all = [];
        foreach ($employees as $employee) {
            foreach ($this->scanEmployeePeriod($employee, $period) as $alert) {
                $all[] = array_merge($alert, [
                    'employee_id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'full_name' => $employee->full_name,
                ]);
            }
        }

        return $all;
    }
}
