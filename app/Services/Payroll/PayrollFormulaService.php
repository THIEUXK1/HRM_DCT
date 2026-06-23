<?php

namespace App\Services\Payroll;

use App\Models\PayrollFormulaRule;
use InvalidArgumentException;

class PayrollFormulaService
{
    public function __construct(
        private readonly PayrollFormulaEvaluator $evaluator,
    ) {}

    /**
     * @return array{
     *   lines: array<int, array<string, mixed>>,
     *   additional_earnings: float,
     *   additional_deductions: float,
     *   context: array<string, mixed>
     * }
     */
    public function apply(int $companyId, array $context): array
    {
        $rules = PayrollFormulaRule::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $lines = [];
        $additionalEarnings = 0.0;
        $additionalDeductions = 0.0;
        $workingContext = $context;

        foreach ($rules as $rule) {
            if (! $this->matchesApplyWhen($rule->apply_when, $workingContext)) {
                continue;
            }

            try {
                $amount = $this->evaluator->evaluate($rule->formula, $workingContext);
            } catch (InvalidArgumentException $e) {
                $lines[] = [
                    'code' => $rule->code,
                    'name' => $rule->name,
                    'target_field' => $rule->target_field,
                    'amount' => 0,
                    'error' => $e->getMessage(),
                    'category' => $rule->category,
                ];

                continue;
            }

            if ($amount <= 0) {
                continue;
            }

            $lines[] = [
                'code' => $rule->code,
                'name' => $rule->name,
                'target_field' => $rule->target_field,
                'formula' => $rule->formula,
                'amount' => $amount,
                'category' => $rule->category,
                'is_taxable' => $rule->is_taxable,
                'apply_when' => $rule->apply_when,
            ];

            $workingContext[$rule->target_field] = $amount;

            if ($rule->category === 'deduction') {
                $additionalDeductions += $amount;
            } else {
                $additionalEarnings += $amount;
            }
        }

        return [
            'lines' => $lines,
            'additional_earnings' => $additionalEarnings,
            'additional_deductions' => $additionalDeductions,
            'context' => $workingContext,
        ];
    }

    private function matchesApplyWhen(string $applyWhen, array $context): bool
    {
        return match ($applyWhen) {
            PayrollFormulaRule::APPLY_TERMINATED => (int) ($context['is_terminated_in_month'] ?? 0) === 1,
            PayrollFormulaRule::APPLY_PERFORMANCE => (int) ($context['has_performance_score'] ?? 0) === 1,
            PayrollFormulaRule::APPLY_ACTIVE => (int) ($context['is_terminated_in_month'] ?? 0) === 0,
            default => true,
        };
    }

    public static function variableHints(?int $companyId = null): array
    {
        if ($companyId) {
            return app(PayrollFormulaVariableService::class)->variableHints($companyId);
        }

        return PayrollFormulaEvaluator::defaultVariableHints();
    }
}
