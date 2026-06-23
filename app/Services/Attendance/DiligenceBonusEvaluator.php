<?php

namespace App\Services\Attendance;

use App\Models\AttendanceSummary;
use App\Services\Payroll\PhasedIncomeCalculator;

/**
 * Đánh giá đủ điều kiện thưởng chuyên cần theo cấu hình công ty.
 */
class DiligenceBonusEvaluator
{
    public function __construct(
        private readonly DiligenceSettingsService $settings,
        private readonly PhasedIncomeCalculator $phasedCalculator,
    ) {}

    /**
     * @return array{
     *   eligible: bool,
     *   bonus_amount: float,
     *   diligence_probation_pay: float,
     *   diligence_official_pay: float,
     *   diligence_phase_mode: string,
     *   grade: string,
     *   disqualify_reasons: array<int, string>,
     *   settings: array<string, mixed>,
     *   has_phase_split: bool
     * }
     */
    public function evaluate(AttendanceSummary $summary, float $attendanceRate): array
    {
        $config = $this->settings->forCompany((int) $summary->company_id);
        $reasons = [];

        if (! $config['enabled']) {
            return $this->emptyResult($config, $attendanceRate, $summary, ['Công ty chưa bật thưởng chuyên cần']);
        }

        $workDays = (float) ($summary->work_days ?? 0);
        $paidLeaveDays = (float) ($summary->paid_leave_days ?? 0);
        if ($workDays <= 0 && $paidLeaveDays <= 0) {
            $reasons[] = 'Không có ngày công và không có nghỉ phép có lương trong tháng';
        }

        if ($attendanceRate < $config['min_attendance_rate']) {
            $reasons[] = "Tỷ lệ chuyên cần {$attendanceRate}% thấp hơn mức yêu cầu {$config['min_attendance_rate']}%";
        }

        if ((int) $summary->late_count > $config['max_late_count']) {
            $reasons[] = 'Đi trễ '.(int) $summary->late_count." lần (vượt hạn mức {$config['max_late_count']})";
        }

        if ((float) $summary->absent_days > $config['max_absent_days']) {
            $reasons[] = 'Vắng không phép '.(float) $summary->absent_days." ngày (vượt hạn mức {$config['max_absent_days']})";
        }

        $forgotCount = (int) ($summary->forgot_punch_count ?? 0);
        if ($forgotCount > $config['max_forgot_punch']) {
            $reasons[] = "Quên chấm công {$forgotCount} lần/tháng (vượt hạn mức {$config['max_forgot_punch']}) — mất thưởng chuyên cần";
        }

        $eligible = count($reasons) === 0;
        $probationDays = (float) ($summary->probation_work_days ?? 0);
        $officialDays = (float) ($summary->official_work_days ?? 0);
        $standardDays = max(1.0, (float) ($summary->standard_work_days ?? 0));
        $hasPhaseSplit = $probationDays > 0 && $officialDays > 0;

        $split = $this->phasedCalculator->calculateMonthly(
            (float) $config['bonus_amount_probation'],
            (float) $config['bonus_amount_official'],
            $probationDays,
            $officialDays,
            $standardDays,
            (string) $config['phase_mode'],
            $eligible,
        );

        return [
            'eligible' => $eligible,
            'bonus_amount' => $split['total'],
            'diligence_probation_pay' => $split['probation'],
            'diligence_official_pay' => $split['official'],
            'diligence_phase_mode' => $split['mode'],
            'grade' => $this->grade(
                $attendanceRate,
                (int) $summary->late_count,
                (float) $summary->absent_days,
                $eligible,
            ),
            'disqualify_reasons' => $reasons,
            'settings' => $config,
            'has_phase_split' => $hasPhaseSplit,
        ];
    }

    public function grade(float $rate, int $lateCount, float $absentDays, bool $bonusEligible): string
    {
        if (! $bonusEligible || $absentDays >= 3 || $rate < 80) {
            return 'Yếu';
        }
        if ($lateCount >= 5 || $rate < 95) {
            return 'Trung bình';
        }
        if ($rate >= 98 && $lateCount <= 1 && $absentDays == 0 && $bonusEligible) {
            return 'Xuất sắc';
        }

        return 'Tốt';
    }

    /** @param  array<int, string>  $reasons */
    private function emptyResult(array $config, float $attendanceRate, AttendanceSummary $summary, array $reasons): array
    {
        return [
            'eligible' => false,
            'bonus_amount' => 0.0,
            'diligence_probation_pay' => 0.0,
            'diligence_official_pay' => 0.0,
            'diligence_phase_mode' => $config['phase_mode'] ?? PhasedIncomeCalculator::MODE_FULL_MONTH,
            'grade' => $this->grade($attendanceRate, (int) $summary->late_count, (float) $summary->absent_days, false),
            'disqualify_reasons' => $reasons,
            'settings' => $config,
            'has_phase_split' => false,
        ];
    }
}
