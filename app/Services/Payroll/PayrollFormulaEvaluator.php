<?php

namespace App\Services\Payroll;

use InvalidArgumentException;

/**
 * Đánh giá biểu thức an toàn: thay {biến} bằng số, chỉ cho phép toán học cơ bản.
 */
class PayrollFormulaEvaluator
{
    /** @deprecated Dùng PayrollFormulaVariableService::variableHints() — giữ cho test cũ */
    public const VARIABLE_HINTS = [];

    /** @return array<string, string> */
    public static function defaultVariableHints(): array
    {
        $computed = config('payroll_formula_variables.computed', []);
        $parameters = [];
        foreach (config('payroll_formula_variables.parameters', []) as $def) {
            if (! empty($def['formula_key'])) {
                $parameters[$def['formula_key']] = $def['label'];
            }
        }

        return array_merge($computed, $parameters, [
            'unused_leave_days' => 'Ngày phép còn lại khi thôi việc',
        ]);
    }

    public function evaluate(string $formula, array $variables): float
    {
        $expression = trim($formula);
        if ($expression === '') {
            return 0.0;
        }

        uksort($variables, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($variables as $key => $value) {
            $expression = str_replace(
                '{'.$key.'}',
                (string) (float) $value,
                $expression,
            );
        }

        if (preg_match('/\{[a-z0-9_]+\}/i', $expression)) {
            throw new InvalidArgumentException('Công thức chứa biến không xác định: '.$expression);
        }

        if (! preg_match('/^[\d+\-*\/().\s]+$/', $expression)) {
            throw new InvalidArgumentException('Công thức chứa ký tự không hợp lệ.');
        }

        $result = eval('return (float) ('.$expression.');');

        return round(max(0, (float) $result), 0);
    }
}
