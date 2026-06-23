<?php



namespace App\Services\Payroll;



use App\Models\Company;

use App\Models\EmployeeReview;

use App\Models\PayrollResult;

use App\Models\PerformanceCycle;

use App\Services\Company\CompanyPolicyResolver;

use Carbon\Carbon;



/**

 * Thưởng NS (dòng 44 phiếu lương) = lương tháng T-1 × điểm KPI tháng T-1.

 * Khi TV→CT cùng kỳ: tách theo lương TV / CT tương ứng.

 */

class PayrollPreviousMonthService

{

    /**

     * @param  array<string, mixed>|null  $currentEarnedBreakdown

     * @return array{

     *   performance_bonus: float,

     *   performance_bonus_probation: float,

     *   performance_bonus_official: float,

     *   performance_bonus_split: bool,

     *   prev_month_base_pay: float,

     *   prev_month_probation_base_pay: float,

     *   prev_month_official_base_pay: float,

     *   prev_month_period: string,

     *   performance_score: float

     * }

     */

    public function resolveBonus(

        int $employeeId,

        int $companyId,

        string $period,

        ?array $currentEarnedBreakdown = null,

    ): array {

        $prevPeriod = Carbon::createFromFormat('!Y-m-d', $period.'-01')->subMonth()->format('Y-m');

        $policy = CompanyPolicyResolver::for($companyId, $period);

        $enabled = $policy->getBool('performance_bonus_enabled', true)

            && $policy->getBool('performance_bonus_use_prev_month', true);

        $splitByPhase = (bool) config('payroll_vn.phased_income.performance_bonus_split_by_phase', true);



        $bonusRate = $policy->getFloat('performance_bonus_rate', 0.15);

        $prevBreakdown = $this->previousBreakdown($employeeId, $companyId, $prevPeriod);

        $prevBasePay = (float) ($prevBreakdown['base_pay_total'] ?? 0);

        $score = $enabled ? $this->performanceScoreForPeriod($employeeId, $companyId, $prevPeriod) : 0.0;



        [$prevProbationBase, $prevOfficialBase] = $this->resolvePreviousBaseParts(

            $prevBreakdown,

            $prevBasePay,

            $currentEarnedBreakdown,

            $splitByPhase,

        );



        $shouldSplit = $splitByPhase && ($prevProbationBase > 0 || $prevOfficialBase > 0)

            && ($prevProbationBase + $prevOfficialBase) > 0;



        if ($enabled && $score > 0 && $shouldSplit) {

            $probationBonus = round($prevProbationBase * $score / 100 * $bonusRate, 0);

            $officialBonus = round($prevOfficialBase * $score / 100 * $bonusRate, 0);

            $bonus = $probationBonus + $officialBonus;

        } elseif ($enabled && $prevBasePay > 0 && $score > 0) {

            $probationBonus = 0.0;

            $officialBonus = 0.0;

            $bonus = round($prevBasePay * $score / 100 * $bonusRate, 0);

        } else {

            $probationBonus = 0.0;

            $officialBonus = 0.0;

            $bonus = 0.0;

        }



        return [

            'performance_bonus' => $bonus,

            'performance_bonus_probation' => $probationBonus,

            'performance_bonus_official' => $officialBonus,

            'performance_bonus_split' => $shouldSplit && $bonus > 0,

            'prev_month_base_pay' => $prevBasePay,

            'prev_month_probation_base_pay' => $prevProbationBase,

            'prev_month_official_base_pay' => $prevOfficialBase,

            'prev_month_period' => $prevPeriod,

            'performance_score' => $score,

        ];

    }



    public function previousBasePayTotal(int $employeeId, int $companyId, string $period): float

    {

        return (float) ($this->previousBreakdown($employeeId, $companyId, $period)['base_pay_total'] ?? 0);

    }



    /** @return array<string, mixed> */

    public function previousBreakdown(int $employeeId, int $companyId, string $period): array

    {

        $result = PayrollResult::query()

            ->where('employee_id', $employeeId)

            ->whereHas('cycle', fn ($q) => $q

                ->where('company_id', $companyId)

                ->where('period', $period))

            ->join('payroll_cycles', 'payroll_cycles.id', '=', 'payroll_results.payroll_cycle_id')
            ->orderByDesc('payroll_cycles.run_number')
            ->orderByDesc('payroll_results.id')
            ->select('payroll_results.*')
            ->first();

        if (! $result) {
            return [];
        }

        return is_array($result->breakdown) ? $result->breakdown : [];
    }



    /**

     * @param  array<string, mixed>  $prevBreakdown

     * @param  array<string, mixed>|null  $currentEarnedBreakdown

     * @return array{0: float, 1: float}

     */

    private function resolvePreviousBaseParts(

        array $prevBreakdown,

        float $prevBasePay,

        ?array $currentEarnedBreakdown,

        bool $splitByPhase,

    ): array {

        $prevProbation = (float) ($prevBreakdown['probation_base_pay'] ?? 0);

        $prevOfficial = (float) ($prevBreakdown['official_base_pay'] ?? 0);



        if ($prevProbation > 0 || $prevOfficial > 0) {

            return [$prevProbation, $prevOfficial];

        }



        if (! $splitByPhase || $prevBasePay <= 0 || ! ($currentEarnedBreakdown['has_phase_split'] ?? false)) {

            return [0.0, $prevBasePay];

        }



        $probDays = (float) ($currentEarnedBreakdown['probation_work_days'] ?? 0);

        $offDays = (float) ($currentEarnedBreakdown['official_work_days'] ?? 0);

        $totalDays = $probDays + $offDays;



        if ($totalDays <= 0) {

            return [0.0, $prevBasePay];

        }



        $prevProbation = round($prevBasePay * ($probDays / $totalDays), 0);



        return [$prevProbation, $prevBasePay - $prevProbation];

    }



    public function performanceScoreForPeriod(int $employeeId, int $companyId, string $period): float

    {

        $company = Company::find($companyId);

        if (! $company?->tenant_id) {

            return 0.0;

        }



        $cycle = PerformanceCycle::where('tenant_id', $company->tenant_id)

            ->where('period', $period)

            ->whereIn('status', ['active', 'closed'])

            ->orderByDesc('id')

            ->first();



        if (! $cycle) {

            return 0.0;

        }



        $review = EmployeeReview::where('employee_id', $employeeId)

            ->where('performance_cycle_id', $cycle->id)

            ->where('status', 'completed')

            ->whereNotNull('final_score')

            ->first();



        return $review ? (float) $review->final_score : 0.0;

    }

}


