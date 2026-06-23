<?php

namespace App\Services\Payroll;

use App\Models\AttendanceLog;
use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Models\EmployeeTermination;
use App\Models\EmploymentContract;
use App\Models\OvertimeRequest;
use App\Services\Attendance\EmploymentPhaseResolver;
use App\Services\Attendance\OvertimeExcessService;
use App\Services\Attendance\AttendanceWorkDaysPhaseSplitter;
use Carbon\Carbon;

/**
 * Tính thu nhập gross theo giai đoạn thử việc / chính thức (BLLĐ 2019 Điều 26).
 *
 * Công thức (chuẩn PM lương VN):
 *   Lương TV = (Lương TV tháng × Công TV) / Công chuẩn
 *   Lương CT = (Lương CT tháng × Công CT) / Công chuẩn
 *   Gross    = Lương TV + Lương CT + OT (theo hệ số 150/200/300%)
 */
class PayrollEarningsService
{
    public function __construct(
        private readonly EmploymentPhaseResolver $phaseResolver,
        private readonly PayrollOtGridPayService $otGridPay,
        private readonly OvertimeExcessService $otExcess,
        private readonly AttendanceWorkDaysPhaseSplitter $workDaysSplitter,
    ) {}

    /**
     * @return array{
     *   gross_salary: float,
     *   insurance_salary_base: float,
     *   breakdown: array<string, mixed>
     * }
     */
    public function calculateGross(
        Employee $employee,
        EmploymentContract $contract,
        AttendanceSummary $summary,
        string $period,
    ): array {
        $standardDays = max(1.0, (float) $summary->standard_work_days);
        $probationDays = (float) $summary->probation_work_days;
        $officialDays = (float) $summary->official_work_days;
        $workDays = (float) $summary->work_days;
        $probationPaidLeave = (float) ($summary->probation_paid_leave_days ?? 0);
        $officialPaidLeave = (float) ($summary->official_paid_leave_days ?? 0);
        $probationUnpaidLeave = (float) ($summary->probation_unpaid_leave_days ?? 0);
        $officialUnpaidLeave = (float) ($summary->official_unpaid_leave_days ?? 0);
        $paidLeaveDays = (float) ($summary->paid_leave_days ?? 0);
        $unpaidLeaveDays = (float) ($summary->unpaid_leave_days ?? 0);

        // Công tính lương = đi làm + nghỉ có hưởng lương (phép, hiếu hỷ…) — tách theo giai đoạn TV/CT
        $payableProbationDays = $probationDays + $probationPaidLeave;
        $payableOfficialDays = $officialDays + $officialPaidLeave;

        $phases = $this->phaseResolver->phasesInPeriod($employee, $period);
        $hasPhaseSplit = count($phases) > 1;

        // Summary thiếu cột TV/CT (import Excel cũ) — phân bổ theo giai đoạn trong tháng
        if ($hasPhaseSplit && $probationDays <= 0 && $officialDays <= 0 && $workDays > 0) {
            $split = $this->workDaysSplitter->splitWorkDays($employee, $period, $workDays);
            $probationDays = $split['probation_work_days'];
            $officialDays = $split['official_work_days'];
            $payableProbationDays = $probationDays + $probationPaidLeave;
            $payableOfficialDays = $officialDays + $officialPaidLeave;
            if ($probationPaidLeave <= 0 && $officialPaidLeave <= 0 && $paidLeaveDays > 0) {
                $leaveSplit = $this->workDaysSplitter->splitPaidLeaveDays($employee, $period, $paidLeaveDays);
                $probationPaidLeave = $leaveSplit['probation_paid_leave_days'];
                $officialPaidLeave = $leaveSplit['official_paid_leave_days'];
                $payableProbationDays = $probationDays + $probationPaidLeave;
                $payableOfficialDays = $officialDays + $officialPaidLeave;
            }
            if ($probationUnpaidLeave <= 0 && $officialUnpaidLeave <= 0 && $unpaidLeaveDays > 0) {
                $unpaidSplit = $this->workDaysSplitter->splitUnpaidLeaveDays($employee, $period, $unpaidLeaveDays);
                $probationUnpaidLeave = $unpaidSplit['probation_unpaid_leave_days'];
                $officialUnpaidLeave = $unpaidSplit['official_unpaid_leave_days'];
            }
        } elseif ($hasPhaseSplit) {
            if ($probationPaidLeave <= 0 && $officialPaidLeave <= 0 && $paidLeaveDays > 0) {
                $leaveSplit = $this->workDaysSplitter->splitPaidLeaveDays($employee, $period, $paidLeaveDays);
                $probationPaidLeave = $leaveSplit['probation_paid_leave_days'];
                $officialPaidLeave = $leaveSplit['official_paid_leave_days'];
                $payableProbationDays = $probationDays + $probationPaidLeave;
                $payableOfficialDays = $officialDays + $officialPaidLeave;
            }
            if ($probationUnpaidLeave <= 0 && $officialUnpaidLeave <= 0 && $unpaidLeaveDays > 0) {
                $unpaidSplit = $this->workDaysSplitter->splitUnpaidLeaveDays($employee, $period, $unpaidLeaveDays);
                $probationUnpaidLeave = $unpaidSplit['probation_unpaid_leave_days'];
                $officialUnpaidLeave = $unpaidSplit['official_unpaid_leave_days'];
            }
        } elseif ($probationDays <= 0 && $officialDays <= 0 && $workDays > 0) {
            // NV không thử việc trong kỳ: toàn bộ công tính chính thức
            $payableOfficialDays = $workDays + $officialPaidLeave;
            if ($officialPaidLeave <= 0 && $paidLeaveDays > 0) {
                $payableOfficialDays = $workDays + $paidLeaveDays;
            }
        }

        // NV thôi việc trong tháng — giới hạn công tính lương đến ngày nghỉ
        $termination = $this->terminationInPeriod($employee, $period);
        if ($termination) {
            [$payableProbationDays, $payableOfficialDays] = $this->capPayableDaysForTermination(
                $payableProbationDays,
                $payableOfficialDays,
                $paidLeaveDays,
                $termination,
            );
        }

        $baseSalary = (float) $contract->salary_base;
        $probationMonthly = (float) ($contract->probation_salary ?: $baseSalary);

        $probationBasePay = $payableProbationDays > 0
            ? round($probationMonthly / $standardDays * $payableProbationDays, 0)
            : 0.0;

        $officialBasePay = $payableOfficialDays > 0
            ? round($baseSalary / $standardDays * $payableOfficialDays, 0)
            : 0.0;

        $ot = config('payroll_vn.use_ot_grid_pay', true)
            ? $this->otGridPay->calculate($employee, $contract, $summary, $period)
            : $this->calculateOvertimePay($employee, $period, $standardDays, $baseSalary, $probationMonthly);

        $excessSummary = $this->otExcess->excessSummaryForPeriod($employee->id, $period);
        $excludedHours = $excessSummary['payroll_excluded_hours'];
        if ($excludedHours > 0 && ($ot['hours_total'] ?? 0) > 0) {
            $ot = $this->applyOtExcessDeduction($ot, $excludedHours);
        }

        $basePayTotal = $probationBasePay + $officialBasePay;

        // Phụ trội ca đêm thường quy (NC_N) — Điều 106 BLLĐ 2019: +30% × A × giờ đêm
        // NC_D (100%) đã nằm trong lương tháng; chỉ cần cộng thêm phần 30%
        $nightWorkPremium = $this->calculateNightWorkPremium(
            $summary,
            $baseSalary,
            $probationMonthly,
            $standardDays,
            $probationDays,
            $officialDays,
        );

        $gross = $basePayTotal + $ot['total'] + $nightWorkPremium;

        $insuranceSalary = (float) ($employee->insurance_salary ?: $contract->insurance_salary ?: $baseSalary);
        $insuranceBase = $this->resolveInsuranceBase(
            $insuranceSalary,
            $standardDays,
            $probationDays,
            $payableProbationDays,
            $payableOfficialDays,
            $contract,
        );

        return [
            'gross_salary' => $gross,
            'insurance_salary_base' => $insuranceBase,
            'breakdown' => [
                'calculation_method' => 'phased_probation_official',
                'legal_reference' => 'Hợp đồng thử việc / chính thức',
                'standard_work_days' => $standardDays,
                'work_days' => $workDays,
                'probation_work_days' => $probationDays,
                'official_work_days' => $officialDays,
                'payable_probation_days' => $payableProbationDays,
                'payable_official_days' => $payableOfficialDays,
                'leave_days' => (float) $summary->leave_days,
                'paid_leave_days' => $paidLeaveDays,
                'unpaid_leave_days' => $unpaidLeaveDays,
                'probation_paid_leave_days' => $probationPaidLeave,
                'official_paid_leave_days' => $officialPaidLeave,
                'probation_unpaid_leave_days' => $probationUnpaidLeave,
                'official_unpaid_leave_days' => $officialUnpaidLeave,
                'payable_probation_leave_days' => $probationPaidLeave,
                'payable_official_leave_days' => $officialPaidLeave,
                'absent_days' => (float) $summary->absent_days,
                'base_salary_monthly' => $baseSalary,
                'probation_salary_monthly' => $probationMonthly,
                'probation_salary_rate' => round($probationMonthly / max(1, $baseSalary), 4),
                'probation_base_pay' => $probationBasePay,
                'official_base_pay' => $officialBasePay,
                'base_pay_total' => $basePayTotal,
                'has_phase_split' => $hasPhaseSplit,
                'phases' => $phases,
                'ot_hours' => $ot['hours_total'],
                'ot_probation_hours' => $ot['hours_probation'],
                'ot_official_hours' => $ot['hours_official'],
                'ot_pay' => $ot['total'],
                'ot_probation_pay' => $ot['probation'],
                'ot_official_pay' => $ot['official'],
                'night_work_premium' => $nightWorkPremium,
                'night_work_hours_total' => (float)($summary->work_night_weekday_hours ?? 0)
                    + (float)($summary->work_night_weekend_hours ?? 0)
                    + (float)($summary->work_night_holiday_hours ?? 0),
                'ot_pay_grid' => $ot['pay_grid'] ?? [],
                'ot_hour_grid' => $ot['hour_grid'] ?? [],
                'ot_day_pay' => $ot['day_pay'] ?? 0,
                'ot_night_pay' => $ot['night_pay'] ?? 0,
                'ot_hourly_rate_official' => $ot['hourly_rate_official'] ?? null,
                'ot_calculation_method' => $ot['calculation_method'] ?? 'legacy',
                'ot_details' => $ot['details'],
                'ot_excess_hours' => $excessSummary['excess_hours'] ?? 0,
                'ot_payroll_excluded_hours' => $ot['payroll_excluded_hours'] ?? 0,
                'ot_payroll_excluded_amount' => $ot['payroll_excluded_amount'] ?? 0,
                'ot_excess_records' => $excessSummary['records'] ?? 0,
                'phased_earnings_basis' => [
                    'base_pay' => 'work_days_by_phase',
                    'ot_pay' => 'occurrence_date_by_phase',
                    'diligence' => 'company_diligence_phase_mode',
                    'allowances' => 'payroll_vn.phased_income.allowance_mode_overrides',
                ],
                'insurance_salary_full' => $insuranceSalary,
                'insurance_salary_applied' => $insuranceBase,
                'bhxh_on_probation' => (bool) config('payroll_vn.probation.bhxh_during_probation', false),
                'is_terminated_in_month' => $termination !== null,
                'termination_date' => $termination['termination_date'] ?? null,
                'work_days_until_exit' => $termination['work_days_until_exit'] ?? null,
                'payable_days_until_exit' => $termination
                    ? ($payableProbationDays + $payableOfficialDays)
                    : null,
            ],
        ];
    }

    /**
     * Phụ trội ca đêm thường quy (NC_N): +30% × A × giờ đêm bình thường.
     * Phân bổ giờ đêm theo tỷ lệ công TV/CT khi có phase split.
     */
    private function calculateNightWorkPremium(
        AttendanceSummary $summary,
        float $baseSalary,
        float $probationMonthly,
        float $standardDays,
        float $probationDays,
        float $officialDays,
    ): float {
        $totalNightH = (float) ($summary->work_night_weekday_hours ?? 0)
            + (float) ($summary->work_night_weekend_hours ?? 0)
            + (float) ($summary->work_night_holiday_hours ?? 0);

        if ($totalNightH <= 0.0 || $standardDays <= 0.0) {
            return 0.0;
        }

        $rate = (float) config('payroll_vn.night_work_premium_rate', 0.30);
        $totalWorkDays = $probationDays + $officialDays;

        // Phase split: phân bổ giờ đêm theo tỷ lệ ngày công
        if ($totalWorkDays > 0 && $probationDays > 0) {
            $proRatio = $probationDays / $totalWorkDays;
            $probationNightH = $totalNightH * $proRatio;
            $officialNightH  = $totalNightH * (1 - $proRatio);
        } else {
            $probationNightH = 0.0;
            $officialNightH  = $totalNightH;
        }

        $hourlyOfficial  = $baseSalary / $standardDays / 8;
        $hourlyProbation = $probationMonthly / $standardDays / 8;

        return round(
            $hourlyProbation * $rate * $probationNightH
            + $hourlyOfficial * $rate * $officialNightH,
            0
        );
    }

    /** @return array{termination_date: string, work_days_until_exit: float}|null */
    private function terminationInPeriod(Employee $employee, string $period): ?array
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $term = EmployeeTermination::where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'completed'])
            ->whereBetween('termination_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('termination_date')
            ->first();

        if (! $term) {
            return null;
        }

        $termDate = Carbon::parse($term->termination_date);
        $workDays = (float) AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $termDate->toDateString()])
            ->whereNotNull('check_in_at')
            ->count();

        return [
            'termination_date' => $termDate->format('Y-m-d'),
            'work_days_until_exit' => $workDays,
        ];
    }

    /** @return array{0: float, 1: float} */
    private function capPayableDaysForTermination(
        float $payableProbation,
        float $payableOfficial,
        float $paidLeaveDays,
        array $termination,
    ): array {
        $workUntilExit = (float) $termination['work_days_until_exit'];
        $maxPayable = $workUntilExit + $paidLeaveDays;
        $total = $payableProbation + $payableOfficial;

        if ($total <= $maxPayable || $total <= 0) {
            return [$payableProbation, $payableOfficial];
        }

        if ($maxPayable <= 0) {
            return [0.0, 0.0];
        }

        $ratio = $maxPayable / $total;

        return [
            round($payableProbation * $ratio, 2),
            round($payableOfficial * $ratio, 2),
        ];
    }

    /**
     * Trừ tiền OT vượt mức — phân bổ theo tỷ lệ tiền OT thực tế TV/CT, không hardcode.
     *
     * @param  array<string, mixed>  $ot
     * @return array<string, mixed>
     */
    private function applyOtExcessDeduction(array $ot, float $excludedHours): array
    {
        $hoursTotal = (float) ($ot['hours_total'] ?? 0);
        if ($hoursTotal <= 0) {
            return $ot;
        }

        $ratio = min(1.0, $excludedHours / $hoursTotal);
        $deduction = round((float) $ot['total'] * $ratio, 0);
        $probationPay = (float) ($ot['probation'] ?? 0);
        $officialPay = (float) ($ot['official'] ?? 0);
        $phasePayTotal = $probationPay + $officialPay;

        if ($phasePayTotal > 0) {
            $probationDeduction = round($deduction * ($probationPay / $phasePayTotal), 0);
            $officialDeduction = $deduction - $probationDeduction;
            $ot['probation'] = max(0, $probationPay - $probationDeduction);
            $ot['official'] = max(0, $officialPay - $officialDeduction);
        } else {
            $ot['probation'] = 0.0;
            $ot['official'] = 0.0;
        }

        $ot['total'] = max(0, (float) $ot['total'] - $deduction);
        $ot['payroll_excluded_hours'] = $excludedHours;
        $ot['payroll_excluded_amount'] = $deduction;

        return $ot;
    }

    /**
     * Tính mức đóng BHXH theo loại hợp đồng (Luật BHXH 2024 + BLLĐ 2019 Điều 24–27).
     *
     * HĐTV riêng (contract_type = 'probation'):
     *   - Chưa ký HĐLĐ → không thuộc diện BHXH bắt buộc trong giai đoạn TV
     *   - Khi chuyển sang HĐLĐ trong tháng: BHXH tính từ ngày CT
     *
     * HĐLĐ có nội dung thử việc (contract_type = fixed_term/indefinite + probation_months > 0):
     *   - HĐLĐ có hiệu lực ngay → BHXH bắt buộc từ tháng 1 (kể cả trong giai đoạn TV)
     *   - Prorate theo tổng công tính lương nếu vào giữa tháng
     */
    private function resolveInsuranceBase(
        float $insuranceSalary,
        float $standardDays,
        float $probationDays,
        float $payableProbationDays,
        float $payableOfficialDays,
        EmploymentContract $contract,
    ): float {
        $totalPayableDays = $payableProbationDays + $payableOfficialDays;

        if ($totalPayableDays <= 0) {
            return 0.0;
        }

        // HĐLĐ (fixed_term / indefinite):
        //   - Có điều khoản TV (probation_months > 0): HĐLĐ có hiệu lực ngay, BHXH toàn bộ TV+CT
        //   - Không có điều khoản TV nhưng probation_days > 0: ngày TV thuộc HĐTV riêng cũ
        //     → BHXH chỉ tính từ ngày HĐLĐ có hiệu lực (payableOfficialDays)
        if ($contract->contract_type !== 'probation') {
            $hasContractProbationClause = (int) ($contract->probation_months ?? 0) > 0;
            $bhxhDuringProbation = (bool) config('payroll_vn.probation.bhxh_during_probation', false);
            $daysForBhxh = ($hasContractProbationClause && $bhxhDuringProbation) ? $totalPayableDays : $payableOfficialDays;

            if ($daysForBhxh <= 0) {
                return 0.0;
            }

            if ($daysForBhxh >= $standardDays) {
                return $insuranceSalary;
            }

            return round($insuranceSalary * $daysForBhxh / $standardDays, 0);
        }

        // HĐTV riêng: không đóng BHXH khi còn trong giai đoạn TV
        if ($probationDays > 0 && $payableOfficialDays <= 0) {
            return 0.0;
        }

        // HĐTV riêng chuyển sang HĐLĐ trong tháng: BHXH tính từ ngày chính thức
        if ($probationDays > 0 && $payableOfficialDays > 0) {
            return round($insuranceSalary * $payableOfficialDays / $standardDays, 0);
        }

        // Cả tháng chính thức (HĐLĐ đã ký)
        if ($payableOfficialDays >= $standardDays) {
            return $insuranceSalary;
        }

        return round($insuranceSalary * $payableOfficialDays / $standardDays, 0);
    }

    /**
     * @return array{
     *   total: float,
     *   probation: float,
     *   official: float,
     *   hours_total: float,
     *   hours_probation: float,
     *   hours_official: float,
     *   details: array<int, array<string, mixed>>
     * }
     */
    private function calculateOvertimePay(
        Employee $employee,
        string $period,
        float $standardDays,
        float $baseSalary,
        float $probationMonthly,
    ): array {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $otRates = config('hr_vn.ot_rates', ['weekday' => 1.5, 'weekend' => 2.0, 'holiday' => 3.0]);

        $requests = OvertimeRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $total = 0.0;
        $probationPay = 0.0;
        $officialPay = 0.0;
        $hoursTotal = 0.0;
        $hoursProbation = 0.0;
        $hoursOfficial = 0.0;
        $details = [];

        foreach ($requests as $ot) {
            $hours = (float) $ot->hours;
            $phase = $this->phaseResolver->phaseOnDate($employee, Carbon::parse($ot->work_date)) ?? 'official';
            $monthlyBase = $phase === 'probation' ? $probationMonthly : $baseSalary;
            $hourlyRate = $monthlyBase / $standardDays / 8;
            $multiplier = (float) ($otRates[$ot->ot_type ?? 'weekday'] ?? 1.5);
            $pay = round($hourlyRate * $hours * $multiplier, 0);

            $total += $pay;
            $hoursTotal += $hours;
            if ($phase === 'probation') {
                $probationPay += $pay;
                $hoursProbation += $hours;
            } else {
                $officialPay += $pay;
                $hoursOfficial += $hours;
            }

            $details[] = [
                'work_date' => $ot->work_date->format('Y-m-d'),
                'hours' => $hours,
                'ot_type' => $ot->ot_type ?? 'weekday',
                'multiplier' => $multiplier,
                'employment_phase' => $phase,
                'pay' => $pay,
            ];
        }

        return [
            'total' => $total,
            'probation' => $probationPay,
            'official' => $officialPay,
            'hours_total' => $hoursTotal,
            'hours_probation' => $hoursProbation,
            'hours_official' => $hoursOfficial,
            'details' => $details,
        ];
    }
}
