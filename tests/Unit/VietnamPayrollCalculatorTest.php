<?php

namespace Tests\Unit;

use App\Services\Payroll\VietnamPayrollCalculator;
use Tests\TestCase;

class VietnamPayrollCalculatorTest extends TestCase
{
    public function test_calculates_bhxh_and_pit(): void
    {
        $calculator = new VietnamPayrollCalculator();
        $result = $calculator->calculate(20_000_000, 0);

        $this->assertGreaterThan(0, $result['bhxh_employee']);
        $this->assertGreaterThan(0, $result['pit_amount']);
        $this->assertLessThan($result['gross_salary'], $result['net_salary']);
    }
}
