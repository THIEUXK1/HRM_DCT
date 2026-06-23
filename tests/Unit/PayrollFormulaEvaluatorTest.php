<?php

namespace Tests\Unit;

use App\Services\Payroll\PayrollFormulaEvaluator;
use Tests\TestCase;

class PayrollFormulaEvaluatorTest extends TestCase
{
    public function test_evaluates_performance_bonus_formula(): void
    {
        $evaluator = new PayrollFormulaEvaluator();
        $amount = $evaluator->evaluate(
            '{base_pay_total} * {performance_score} / 100 * {performance_bonus_rate}',
            [
                'base_pay_total' => 20_000_000,
                'performance_score' => 90,
                'performance_bonus_rate' => 0.15,
            ],
        );

        $this->assertEquals(2_700_000, $amount);
    }

    public function test_rejects_unknown_variables(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PayrollFormulaEvaluator())->evaluate('{unknown_var} + 1', []);
    }
}
