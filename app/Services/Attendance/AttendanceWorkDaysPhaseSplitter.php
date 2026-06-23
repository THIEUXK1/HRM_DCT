<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use Carbon\Carbon;

/**
 * Phân bổ công tháng theo giai đoạn thử việc / chính thức khi chỉ có tổng công
 * (import Excel, summary thiếu cột TV/CT).
 */
class AttendanceWorkDaysPhaseSplitter
{
    public function __construct(
        private readonly EmploymentPhaseResolver $phaseResolver,
    ) {}

    /**
     * @return array{probation_work_days: float, official_work_days: float}
     */
    public function splitWorkDays(Employee $employee, string $period, float $totalWorkDays): array
    {
        $totalWorkDays = max(0, round($totalWorkDays, 2));
        if ($totalWorkDays <= 0) {
            return ['probation_work_days' => 0.0, 'official_work_days' => 0.0];
        }

        $weights = $this->phaseStandardWeights($employee, $period);
        if ($weights === null) {
            return [
                'probation_work_days' => 0.0,
                'official_work_days' => $totalWorkDays,
            ];
        }

        $probationWeight = $weights['probation'] ?? 0.0;
        $officialWeight = $weights['official'] ?? 0.0;
        $totalWeight = $probationWeight + $officialWeight;

        if ($totalWeight <= 0) {
            $half = round($totalWorkDays / 2, 2);

            return [
                'probation_work_days' => $half,
                'official_work_days' => round($totalWorkDays - $half, 2),
            ];
        }

        $probationDays = round($totalWorkDays * ($probationWeight / $totalWeight), 2);
        $officialDays = round($totalWorkDays - $probationDays, 2);

        return [
            'probation_work_days' => $probationDays,
            'official_work_days' => $officialDays,
        ];
    }

    /**
     * Phân bổ nghỉ có lương theo tỷ trọng công chuẩn từng giai đoạn (fallback import Excel).
     *
     * @return array{probation_paid_leave_days: float, official_paid_leave_days: float}
     */
    public function splitPaidLeaveDays(Employee $employee, string $period, float $paidLeaveDays): array
    {
        $split = $this->splitQuantityByPhaseWeights($employee, $period, $paidLeaveDays);

        return [
            'probation_paid_leave_days' => $split['probation'],
            'official_paid_leave_days' => $split['official'],
        ];
    }

    /**
     * @return array{probation_unpaid_leave_days: float, official_unpaid_leave_days: float}
     */
    public function splitUnpaidLeaveDays(Employee $employee, string $period, float $unpaidLeaveDays): array
    {
        $split = $this->splitQuantityByPhaseWeights($employee, $period, $unpaidLeaveDays);

        return [
            'probation_unpaid_leave_days' => $split['probation'],
            'official_unpaid_leave_days' => $split['official'],
        ];
    }

    /**
     * @return array{
     *   probation_paid_leave_days: float,
     *   official_paid_leave_days: float,
     *   probation_unpaid_leave_days: float,
     *   official_unpaid_leave_days: float
     * }
     */
    public function splitAllLeaveDaysByPhaseWeights(
        Employee $employee,
        string $period,
        float $paidLeaveDays,
        float $unpaidLeaveDays,
    ): array {
        $paid = $this->splitPaidLeaveDays($employee, $period, $paidLeaveDays);
        $unpaid = $this->splitUnpaidLeaveDays($employee, $period, $unpaidLeaveDays);

        return array_merge($paid, $unpaid);
    }

    /**
     * @return array{probation: float, official: float}
     */
    private function splitQuantityByPhaseWeights(Employee $employee, string $period, float $quantity): array
    {
        $quantity = max(0, round($quantity, 2));
        if ($quantity <= 0) {
            return ['probation' => 0.0, 'official' => 0.0];
        }

        $split = $this->splitWorkDays($employee, $period, $quantity);

        return [
            'probation' => $split['probation_work_days'],
            'official' => $split['official_work_days'],
        ];
    }

    /**
     * @return array{probation: float, official: float}|null null = cả tháng một giai đoạn
     */
    public function phaseStandardWeights(Employee $employee, string $period): ?array
    {
        $phases = $this->phaseResolver->phasesInPeriod($employee, $period);
        if (count($phases) <= 1) {
            return null;
        }

        $weights = ['probation' => 0.0, 'official' => 0.0];
        foreach ($phases as $phase) {
            $from = Carbon::parse($phase['from']);
            $to = Carbon::parse($phase['to']);
            $weights[$phase['phase']] = (float) VietnamHolidayService::standardWorkDaysInRange($from, $to);
        }

        return $weights;
    }
}
