<?php

namespace App\Services\Payroll;

use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Services\Attendance\AttendanceOtGridCalculator;
use App\Services\Attendance\EmploymentPhaseResolver;
use App\Services\Attendance\VietnamHolidayService;
use Carbon\Carbon;

/**
 * Tính tiền OT theo lưới BestPacific — ca ngày/đêm × hệ số 150–390%.
 */
class PayrollOtGridPayService
{
    public function __construct(
        private readonly EmploymentPhaseResolver $phaseResolver,
        private readonly AttendanceOtGridCalculator $otGridCalculator,
    ) {}

    /**
     * @return array{
     *   total: float,
     *   probation: float,
     *   official: float,
     *   hours_total: float,
     *   hours_probation: float,
     *   hours_official: float,
     *   pay_grid: array<string, float>,
     *   hour_grid: array<string, float>,
     *   day_pay: float,
     *   night_pay: float,
     *   hourly_rate_official: float,
     *   hourly_rate_probation: float,
     *   calculation_method: string,
     *   details: array<int, array<string, mixed>>
     * }
     */
    public function calculate(
        Employee $employee,
        EmploymentContract $contract,
        AttendanceSummary $summary,
        string $period,
    ): array {
        $standardDays = max(1.0, (float) $summary->standard_work_days);
        $baseSalary = (float) $contract->salary_base;
        $probationMonthly = (float) ($contract->probation_salary ?: $baseSalary);

        $hourlyOfficial = $baseSalary / $standardDays / 8;
        $hourlyProbation = $probationMonthly / $standardDays / 8;
        $multipliers = config('payroll_vn.ot_grid_multipliers', []);

        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $holidays = VietnamHolidayService::forYear($start->year);

        $requests = OvertimeRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $hourGrid = $this->otGridCalculator->calculate($employee, $requests, $start, $end, $holidays);

        $payGrid = [];
        foreach (array_keys($hourGrid) as $key) {
            $payGrid[$key] = 0.0;
        }

        $total = 0.0;
        $probationPay = 0.0;
        $officialPay = 0.0;
        $hoursTotal = 0.0;
        $hoursProbation = 0.0;
        $hoursOfficial = 0.0;
        $details = [];

        $annualLeaveDates = $this->leaveDatesByType($employee, $start, $end, ['PHEP']);
        $paidLeaveDates = $this->leaveDatesByType($employee, $start, $end, paidOnly: true);

        foreach ($requests as $ot) {
            $workDate = Carbon::parse($ot->work_date);
            $dateKey = $workDate->format('Y-m-d');
            $totalHours = (float) $ot->hours;
            $nightHours = min((float) ($ot->night_hours ?? 0), $totalHours);
            $dayHours = round(max(0, $totalHours - $nightHours), 2);

            $phase = $this->phaseResolver->phaseOnDate($employee, $workDate) ?? 'official';
            $hourly = $phase === 'probation' ? $hourlyProbation : $hourlyOfficial;

            $portions = $this->classifyPortions($dateKey, $dayHours, $nightHours, $ot->ot_type ?? 'weekday', $annualLeaveDates, $paidLeaveDates);

            foreach ($portions as $portion) {
                if ($portion['hours'] <= 0) {
                    continue;
                }

                $mult = (float) ($multipliers[$portion['key']] ?? 1.5);
                $pay = round($hourly * $portion['hours'] * $mult, 0);

                $payGrid[$portion['key']] = ($payGrid[$portion['key']] ?? 0) + $pay;
                $total += $pay;

                if ($phase === 'probation') {
                    $probationPay += $pay;
                    $hoursProbation += $portion['hours'];
                } else {
                    $officialPay += $pay;
                    $hoursOfficial += $portion['hours'];
                }

                $hoursTotal += $portion['hours'];

                $details[] = [
                    'work_date' => $dateKey,
                    'grid_key' => $portion['key'],
                    'hours' => $portion['hours'],
                    'multiplier' => $mult,
                    'employment_phase' => $phase,
                    'hourly_rate' => round($hourly, 0),
                    'pay' => $pay,
                ];
            }
        }

        [$dayPay, $nightPay] = $this->splitDayNightPay($payGrid);

        return [
            'total' => round($total, 0),
            'probation' => round($probationPay, 0),
            'official' => round($officialPay, 0),
            'hours_total' => round($hoursTotal, 2),
            'hours_probation' => round($hoursProbation, 2),
            'hours_official' => round($hoursOfficial, 2),
            'pay_grid' => array_map(fn ($v) => round((float) $v, 0), $payGrid),
            'hour_grid' => $hourGrid,
            'day_pay' => round($dayPay, 0),
            'night_pay' => round($nightPay, 0),
            'hourly_rate_official' => round($hourlyOfficial, 0),
            'hourly_rate_probation' => round($hourlyProbation, 0),
            'calculation_method' => 'bpvn_ot_grid',
            'details' => $details,
        ];
    }

    /**
     * @param  array<string, float>  $payGrid
     * @return array{0: float, 1: float}
     */
    private function splitDayNightPay(array $payGrid): array
    {
        $dayPay = 0.0;
        $nightPay = 0.0;

        foreach ($payGrid as $key => $amount) {
            if (str_starts_with($key, 'night_')) {
                $nightPay += (float) $amount;
            } elseif (str_starts_with($key, 'day_')) {
                $dayPay += (float) $amount;
            }
        }

        return [$dayPay, $nightPay];
    }

    /**
     * @param  array<string, true>  $annualLeaveDates
     * @param  array<string, true>  $paidLeaveDates
     * @return list<array{key: string, hours: float}>
     */
    private function classifyPortions(
        string $dateKey,
        float $dayHours,
        float $nightHours,
        string $otType,
        array $annualLeaveDates,
        array $paidLeaveDates,
    ): array {
        if (isset($annualLeaveDates[$dateKey])) {
            return [
                ['key' => 'day_annual_leave', 'hours' => $dayHours],
                ['key' => 'night_annual_leave', 'hours' => $nightHours],
            ];
        }

        $portions = [];
        $remainingNight = $nightHours;

        // night_paid_holiday (270%) chỉ dùng cho OT đêm ngày thường khi NLĐ đang nghỉ bù/hưởng lương.
        // Ngày lễ (ot_type='holiday') phải trả 390% dù có đơn nghỉ LE đã duyệt — NĐ 145/2020 Điều 107.
        if ($remainingNight > 0 && $otType !== 'holiday' && isset($paidLeaveDates[$dateKey])) {
            $portions[] = ['key' => 'night_paid_holiday', 'hours' => $remainingNight];
            $remainingNight = 0.0;
        }

        // N1 (200%): TC đêm không có TC ngày cùng record
        // N2 (210%): TC đêm sau TC ngày cùng record — NĐ 145/2020 Điều 107
        if ($otType === 'weekend') {
            [$dayKey, $nightKey] = ['day_weekend', 'night_weekend'];
        } elseif ($otType === 'holiday') {
            [$dayKey, $nightKey] = ['day_holiday', 'night_holiday'];
        } else {
            $dayKey = 'day_weekday';
            $nightKey = ($dayHours > 0 && $nightHours > 0) ? 'night_weekday_n2' : 'night_weekday_n1';
        }

        if ($dayHours > 0) {
            $portions[] = ['key' => $dayKey, 'hours' => $dayHours];
        }
        if ($remainingNight > 0) {
            $portions[] = ['key' => $nightKey, 'hours' => $remainingNight];
        }

        return $portions;
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
