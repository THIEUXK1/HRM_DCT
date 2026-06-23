<?php

namespace App\Services\Payroll;

/**
 * Tách khoản thu nhập theo tháng (chuyên cần, phụ cấp cố định…) khi TV→CT cùng kỳ.
 *
 * Modes:
 * - full_month: trả nguyên mức tháng nếu đủ điều kiện
 * - prorate_by_days: mức TV/CT khác nhau × công từng giai đoạn / công chuẩn
 * - end_of_period_official: xét trạng thái cuối kỳ — có công CT thì hưởng mức CT
 * - official_from_start_date: chỉ tính trên công CT (phụ cấp trách nhiệm…)
 * - probation_only: chỉ tính trên công TV
 * - per_work_day: mức tháng / công chuẩn × công thực tế từng ngày (ăn ca…)
 */
class PhasedIncomeCalculator
{
    public const MODE_FULL_MONTH = 'full_month';
    public const MODE_PRORATE_BY_DAYS = 'prorate_by_days';
    public const MODE_END_OF_PERIOD_OFFICIAL = 'end_of_period_official';
    public const MODE_OFFICIAL_FROM_START = 'official_from_start_date';
    public const MODE_PROBATION_ONLY = 'probation_only';
    public const MODE_PER_WORK_DAY = 'per_work_day';

    /**
     * @return array{
     *   total: float,
     *   probation: float,
     *   official: float,
     *   mode: string,
     *   has_phase_split: bool
     * }
     */
    public function calculateMonthly(
        float $probationMonthly,
        float $officialMonthly,
        float $probationWorkDays,
        float $officialWorkDays,
        float $standardDays,
        string $mode,
        bool $eligible = true,
    ): array {
        if (! $eligible) {
            return $this->result(0, 0, 0, $mode, $this->hasPhaseSplit($probationWorkDays, $officialWorkDays));
        }

        $standardDays = max(1.0, $standardDays);
        $totalWorkDays = $probationWorkDays + $officialWorkDays;
        $hasPhaseSplit = $this->hasPhaseSplit($probationWorkDays, $officialWorkDays);

        if ($totalWorkDays <= 0) {
            return $this->result(0, 0, 0, $mode, $hasPhaseSplit);
        }

        if (! $hasPhaseSplit) {
            $workDays = $totalWorkDays;
            $monthly = $officialMonthly > 0 ? $officialMonthly : $probationMonthly;
            $useOfficialPhase = $officialWorkDays > 0 || ($probationWorkDays <= 0 && $officialMonthly > 0);

            if ($mode === self::MODE_PER_WORK_DAY) {
                $total = round($monthly / $standardDays * $workDays, 0);

                return $this->result(
                    $total,
                    $useOfficialPhase ? 0.0 : $total,
                    $useOfficialPhase ? $total : 0.0,
                    $mode,
                    false,
                );
            }

            if ($mode === self::MODE_FULL_MONTH) {
                return $this->fullMonth($monthly, $mode);
            }

            $total = round($monthly / $standardDays * $workDays, 0);

            return $this->result(
                $total,
                $useOfficialPhase ? 0.0 : $total,
                $useOfficialPhase ? $total : 0.0,
                $mode,
                false,
            );
        }

        return match ($mode) {
            self::MODE_PER_WORK_DAY => $this->perWorkDay(
                $officialMonthly,
                $probationWorkDays,
                $officialWorkDays,
                $standardDays,
                $mode,
            ),
            self::MODE_PRORATE_BY_DAYS => $this->prorateByDays(
                $probationMonthly,
                $officialMonthly,
                $probationWorkDays,
                $officialWorkDays,
                $standardDays,
                $mode,
            ),
            self::MODE_END_OF_PERIOD_OFFICIAL => $this->endOfPeriodOfficial(
                $probationMonthly,
                $officialMonthly,
                $officialWorkDays,
                $mode,
            ),
            self::MODE_OFFICIAL_FROM_START => $this->officialFromStart(
                $officialMonthly,
                $officialWorkDays,
                $standardDays,
                $mode,
            ),
            self::MODE_PROBATION_ONLY => $this->probationOnly(
                $probationMonthly,
                $probationWorkDays,
                $standardDays,
                $mode,
            ),
            default => $this->fullMonth($officialMonthly, $mode),
        };
    }

    /** @return array{total: float, probation: float, official: float, mode: string, has_phase_split: bool} */
    private function perWorkDay(
        float $monthlyAmount,
        float $probationWorkDays,
        float $officialWorkDays,
        float $standardDays,
        string $mode,
    ): array {
        $dailyRate = $monthlyAmount / $standardDays;
        $probationPay = round($dailyRate * $probationWorkDays, 0);
        $officialPay = round($dailyRate * $officialWorkDays, 0);

        return $this->result(
            $probationPay + $officialPay,
            $probationPay,
            $officialPay,
            $mode,
            true,
        );
    }

    /** @return array{total: float, probation: float, official: float, mode: string, has_phase_split: bool} */
    private function prorateByDays(
        float $probationMonthly,
        float $officialMonthly,
        float $probationWorkDays,
        float $officialWorkDays,
        float $standardDays,
        string $mode,
    ): array {
        $probationPay = $probationWorkDays > 0
            ? round($probationMonthly / $standardDays * $probationWorkDays, 0)
            : 0.0;
        $officialPay = $officialWorkDays > 0
            ? round($officialMonthly / $standardDays * $officialWorkDays, 0)
            : 0.0;

        return $this->result(
            $probationPay + $officialPay,
            $probationPay,
            $officialPay,
            $mode,
            true,
        );
    }

    /** @return array{total: float, probation: float, official: float, mode: string, has_phase_split: bool} */
    private function endOfPeriodOfficial(
        float $probationMonthly,
        float $officialMonthly,
        float $officialWorkDays,
        string $mode,
    ): array {
        if ($officialWorkDays > 0) {
            return $this->result($officialMonthly, 0, $officialMonthly, $mode, true);
        }

        if ($probationMonthly <= 0) {
            return $this->result(0, 0, 0, $mode, true);
        }

        return $this->result($probationMonthly, $probationMonthly, 0, $mode, true);
    }

    /** @return array{total: float, probation: float, official: float, mode: string, has_phase_split: bool} */
    private function officialFromStart(
        float $officialMonthly,
        float $officialWorkDays,
        float $standardDays,
        string $mode,
    ): array {
        $officialPay = $officialWorkDays > 0
            ? round($officialMonthly / $standardDays * $officialWorkDays, 0)
            : 0.0;

        return $this->result($officialPay, 0, $officialPay, $mode, true);
    }

    /** @return array{total: float, probation: float, official: float, mode: string, has_phase_split: bool} */
    private function probationOnly(
        float $probationMonthly,
        float $probationWorkDays,
        float $standardDays,
        string $mode,
    ): array {
        $probationPay = $probationWorkDays > 0
            ? round($probationMonthly / $standardDays * $probationWorkDays, 0)
            : 0.0;

        return $this->result($probationPay, $probationPay, 0, $mode, true);
    }

    /** @return array{total: float, probation: float, official: float, mode: string, has_phase_split: bool} */
    private function fullMonth(float $monthlyAmount, string $mode): array
    {
        return $this->result($monthlyAmount, 0, $monthlyAmount, $mode, false);
    }

    /** @return array{total: float, probation: float, official: float, mode: string, has_phase_split: bool} */
    private function result(float $total, float $probation, float $official, string $mode, bool $hasPhaseSplit): array
    {
        return [
            'total' => round($total, 0),
            'probation' => round($probation, 0),
            'official' => round($official, 0),
            'mode' => $mode,
            'has_phase_split' => $hasPhaseSplit,
        ];
    }

    private function hasPhaseSplit(float $probationWorkDays, float $officialWorkDays): bool
    {
        return $probationWorkDays > 0 && $officialWorkDays > 0;
    }

    public function resolveAllowanceMode(string $allowanceCode): string
    {
        $overrides = config('payroll_vn.phased_income.allowance_mode_overrides', []);

        return (string) ($overrides[$allowanceCode]
            ?? config('payroll_vn.phased_income.allowance_default_mode', self::MODE_FULL_MONTH));
    }
}
