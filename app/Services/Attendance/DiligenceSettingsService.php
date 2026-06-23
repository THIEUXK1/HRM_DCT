<?php

namespace App\Services\Attendance;

use App\Services\Company\CompanyPolicyResolver;
use App\Services\Payroll\PhasedIncomeCalculator;

/**
 * Cấu hình thưởng chuyên cần — admin chỉnh qua Settings (company_settings).
 */
class DiligenceSettingsService
{
    public const KEYS = [
        'diligence_bonus_enabled',
        'diligence_bonus_amount',
        'diligence_bonus_amount_probation',
        'diligence_bonus_amount_official',
        'diligence_phase_mode',
        'diligence_min_attendance_rate',
        'diligence_max_late_count',
        'diligence_max_absent_days',
        'diligence_max_forgot_punch',
        // legacy — map sang prorate_by_days nếu = 1
        'diligence_prorate_on_phase_split',
    ];

    public function defaults(): array
    {
        return [
            'diligence_bonus_enabled' => '1',
            'diligence_bonus_amount' => '500000',
            'diligence_bonus_amount_probation' => '',
            'diligence_bonus_amount_official' => '',
            'diligence_phase_mode' => PhasedIncomeCalculator::MODE_FULL_MONTH,
            'diligence_min_attendance_rate' => '98',
            'diligence_max_late_count' => '1',
            'diligence_max_absent_days' => '0',
            'diligence_max_forgot_punch' => '2',
            'diligence_prorate_on_phase_split' => '0',
        ];
    }

    public function forCompany(int $companyId): array
    {
        $policy = CompanyPolicyResolver::for($companyId);
        $merged = [];
        foreach (self::KEYS as $key) {
            $merged[$key] = $policy->getString($key, $this->defaults()[$key] ?? '');
        }

        $baseAmount = (float) ($merged['diligence_bonus_amount'] ?? 500000);
        $probationAmount = (float) ($merged['diligence_bonus_amount_probation'] ?: 0);
        $officialAmount = (float) ($merged['diligence_bonus_amount_official'] ?: 0);
        if ($probationAmount <= 0) {
            $probationAmount = $baseAmount;
        }
        if ($officialAmount <= 0) {
            $officialAmount = $baseAmount;
        }

        $phaseMode = (string) ($merged['diligence_phase_mode'] ?: PhasedIncomeCalculator::MODE_FULL_MONTH);
        if (($merged['diligence_prorate_on_phase_split'] ?? '0') === '1'
            && $phaseMode === PhasedIncomeCalculator::MODE_FULL_MONTH) {
            $phaseMode = PhasedIncomeCalculator::MODE_PRORATE_BY_DAYS;
        }

        return [
            'enabled' => ($merged['diligence_bonus_enabled'] ?? '1') === '1',
            'bonus_amount' => $baseAmount,
            'bonus_amount_probation' => $probationAmount,
            'bonus_amount_official' => $officialAmount,
            'phase_mode' => $phaseMode,
            'min_attendance_rate' => (float) ($merged['diligence_min_attendance_rate'] ?? 98),
            'max_late_count' => (int) ($merged['diligence_max_late_count'] ?? 1),
            'max_absent_days' => (float) ($merged['diligence_max_absent_days'] ?? 0),
            'max_forgot_punch' => (int) ($merged['diligence_max_forgot_punch'] ?? 2),
            'raw' => $merged,
        ];
    }
}
