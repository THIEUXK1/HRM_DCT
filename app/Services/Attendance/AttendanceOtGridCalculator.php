<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Tách giờ OT theo lưới BestPacific (cột P–X): ca ngày/đêm × loại ngày.
 */
class AttendanceOtGridCalculator
{
    public function __construct(
        private readonly EmploymentPhaseResolver $phaseResolver,
    ) {}

    /** @return array<string, float> */
    public function calculate(
        Employee $employee,
        Collection $otRequests,
        Carbon $start,
        Carbon $end,
        array $holidays,
    ): array {
        return $this->calculateGrid($employee, $otRequests, $start, $end, $holidays);
    }

    /**
     * Lưới OT P–X tách theo giai đoạn thử việc / chính thức (cùng kỳ TV→CT).
     *
     * @return array{
     *   probation: array<string, float>,
     *   official: array<string, float>,
     *   totals: array{probation_hours: float, official_hours: float}
     * }
     */
    public function calculateByPhase(
        Employee $employee,
        Collection $otRequests,
        Carbon $start,
        Carbon $end,
        array $holidays,
        ?Carbon $probationEndDate = null,
    ): array {
        $probationGrid = $this->calculateGrid($employee, $otRequests, $start, $end, $holidays, 'probation', $probationEndDate);
        $officialGrid = $this->calculateGrid($employee, $otRequests, $start, $end, $holidays, 'official', $probationEndDate);

        return [
            'probation' => $probationGrid,
            'official' => $officialGrid,
            'totals' => [
                'probation_hours' => round(array_sum($probationGrid), 2),
                'official_hours' => round(array_sum($officialGrid), 2),
            ],
        ];
    }

    /** @return array<string, float> */
    private function calculateGrid(
        Employee $employee,
        Collection $otRequests,
        Carbon $start,
        Carbon $end,
        array $holidays,
        ?string $phaseFilter = null,
        ?Carbon $probationEndDate = null,
    ): array {
        $grid = [];
        foreach (array_keys(config('attendance_vn.ot_grid_keys', [])) as $key) {
            $grid[$key] = 0.0;
        }

        $annualLeaveDates = $this->leaveDatesByType($employee, $start, $end, ['PHEP']);
        $paidLeaveDates = $this->leaveDatesByType($employee, $start, $end, null, paidOnly: true);

        foreach ($otRequests as $ot) {
            if ($ot->status !== 'approved') {
                continue;
            }

            $workDate = Carbon::parse($ot->work_date);

            if ($phaseFilter !== null) {
                $phase = $this->phaseResolver->phaseOnDate($employee, $workDate, $probationEndDate) ?? 'official';
                if ($phase !== $phaseFilter) {
                    continue;
                }
            }
            $dateKey = $workDate->format('Y-m-d');
            $total = (float) $ot->hours;
            $night = min((float) ($ot->night_hours ?? 0), $total);
            $day = round(max(0, $total - $night), 2);
            $otType = $ot->ot_type ?? 'weekday';

            if (isset($annualLeaveDates[$dateKey])) {
                $grid['day_annual_leave'] += $day;
                $grid['night_annual_leave'] += $night;

                continue;
            }

            if ($night > 0 && isset($paidLeaveDates[$dateKey]) && ! isset($annualLeaveDates[$dateKey])) {
                $grid['night_paid_holiday'] += $night;
                $night = 0.0;
            }

            // N1 (200%): TC đêm không có TC ngày cùng record
            // N2 (210%): TC đêm sau TC ngày cùng record — NĐ 145/2020 Điều 107
            if ($otType === 'weekend') {
                [$dayKey, $nightKey] = ['day_weekend', 'night_weekend'];
            } elseif ($otType === 'holiday') {
                [$dayKey, $nightKey] = ['day_holiday', 'night_holiday'];
            } else {
                $dayKey = 'day_weekday';
                $nightKey = ($day > 0 && $night > 0) ? 'night_weekday_n2' : 'night_weekday_n1';
            }

            $grid[$dayKey] += $day;
            $grid[$nightKey] += $night;
        }

        foreach ($grid as $key => $value) {
            $grid[$key] = round($value, 2);
        }

        $grid['night_weekday'] = round(($grid['night_weekday_n1'] ?? 0.0) + ($grid['night_weekday_n2'] ?? 0.0), 2);

        return $grid;
    }

    /**
     * @param  list<string>|null  $typeCodes
     * @return array<string, true>
     */
    private function leaveDatesByType(
        Employee $employee,
        Carbon $start,
        Carbon $end,
        ?array $typeCodes = null,
        bool $paidOnly = false,
    ): array {
        $query = LeaveRequest::with('leaveType:id,code,is_paid')
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString());

        if ($typeCodes !== null) {
            $query->whereHas('leaveType', fn ($q) => $q->whereIn('code', $typeCodes));
        }

        if ($paidOnly) {
            $query->whereHas('leaveType', fn ($q) => $q->where('is_paid', true));
        }

        $dates = [];
        foreach ($query->get() as $req) {
            $cursor = Carbon::parse($req->start_date)->max($start);
            $reqEnd = Carbon::parse($req->end_date)->min($end);
            while ($cursor <= $reqEnd) {
                $dates[$cursor->format('Y-m-d')] = true;
                $cursor->addDay();
            }
        }

        return $dates;
    }
}
