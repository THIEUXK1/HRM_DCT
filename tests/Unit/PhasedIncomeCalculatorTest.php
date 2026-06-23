<?php

namespace Tests\Unit;

use App\Services\Payroll\PhasedIncomeCalculator;
use Tests\TestCase;

class PhasedIncomeCalculatorTest extends TestCase
{
    public function test_zero_work_days_returns_zero_for_non_split_allowance(): void
    {
        $calc = new PhasedIncomeCalculator();
        $result = $calc->calculateMonthly(
            500_000,
            500_000,
            0,
            0,
            25,
            PhasedIncomeCalculator::MODE_FULL_MONTH,
            true,
        );

        $this->assertEquals(0, $result['total']);
    }

    public function test_non_split_does_not_pay_full_probation_when_official_days_zero(): void
    {
        $calc = new PhasedIncomeCalculator();
        $result = $calc->calculateMonthly(
            300_000,
            500_000,
            0,
            10,
            25,
            PhasedIncomeCalculator::MODE_PRORATE_BY_DAYS,
            true,
        );

        $this->assertEquals(200_000, $result['total']);
    }
}
