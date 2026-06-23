<?php

namespace App\Services\Payroll;

use App\Models\CompanySetting;
use App\Models\PayrollFormulaCustomVariable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PayrollFormulaVariableService
{
    /** @return list<string> */
    public function reservedKeys(): array
    {
        $computed = array_keys(config('payroll_formula_variables.computed', []));
        $parameters = [];
        foreach (config('payroll_formula_variables.parameters', []) as $def) {
            if (! empty($def['formula_key'])) {
                $parameters[] = $def['formula_key'];
            }
        }

        return array_values(array_unique(array_merge($computed, $parameters)));
    }

    /**
     * @return array{
     *   parameters: array<int, array<string, mixed>>,
     *   computed: array<string, string>,
     *   custom_variables: array<int, array<string, mixed>>,
     *   variable_hints: array<string, string>
     * }
     */
    public function catalog(int $companyId): array
    {
        $custom = $this->activeCustomVariables($companyId);

        return [
            'parameters' => $this->parametersWithValues($companyId),
            'computed' => config('payroll_formula_variables.computed', []),
            'custom_variables' => $custom->values()->all(),
            'variable_hints' => $this->variableHints($companyId),
        ];
    }

    /** @return array<string, string> */
    public function variableHints(int $companyId): array
    {
        $hints = config('payroll_formula_variables.computed', []);

        foreach (config('payroll_formula_variables.parameters', []) as $key => $def) {
            $formulaKey = $def['formula_key'] ?? null;
            if ($formulaKey) {
                $hints[$formulaKey] = $def['label'];
            }
        }

        foreach ($this->activeCustomVariables($companyId) as $row) {
            $hints[$row->code] = $row->label.($row->description ? ' — '.$row->description : '');
        }

        return $hints;
    }

    /**
     * Gộp biến tùy chỉnh vào context trước khi evaluate công thức.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function enrichContext(array $context, int $companyId): array
    {
        foreach ($this->activeCustomVariables($companyId) as $variable) {
            $context[$variable->code] = (float) $variable->value;
        }

        $policyRate = $context['performance_bonus_rate'] ?? null;
        if ($policyRate === null) {
            $resolver = \App\Services\Company\CompanyPolicyResolver::for($companyId);
            $context['performance_bonus_rate'] = $resolver->getFloat('performance_bonus_rate', 0.15);
        }

        if (! isset($context['sales_commission_rate'])) {
            $resolver = \App\Services\Company\CompanyPolicyResolver::for($companyId);
            $context['sales_commission_rate'] = $resolver->getFloat('sales_commission_rate', 0.0);
        }

        return $context;
    }

    /** @param  array<string, string|int|float|bool>  $values */
    public function updateParameters(int $companyId, array $values): void
    {
        $definitions = config('payroll_formula_variables.parameters', []);

        foreach ($values as $key => $value) {
            if (! array_key_exists($key, $definitions)) {
                throw new InvalidArgumentException("Tham số không hợp lệ: {$key}");
            }

            $this->assertParameterValue($key, $definitions[$key], $value);

            CompanySetting::updateOrCreate(
                ['company_id' => $companyId, 'key' => $key],
                ['value' => $this->normalizeStoredValue($definitions[$key], $value)],
            );
        }

        \App\Services\Company\CompanyPolicyResolver::flushCache();
    }

    public function createCustomVariable(int $companyId, array $data): PayrollFormulaCustomVariable
    {
        $code = $this->normalizeCustomCode($data['code']);
        $this->assertCustomCodeAvailable($companyId, $code);

        return PayrollFormulaCustomVariable::create([
            'company_id' => $companyId,
            'code' => $code,
            'label' => $data['label'],
            'value' => $data['value'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    public function updateCustomVariable(PayrollFormulaCustomVariable $variable, array $data): PayrollFormulaCustomVariable
    {
        if (isset($data['code'])) {
            $code = $this->normalizeCustomCode($data['code']);
            if ($code !== $variable->code) {
                $this->assertCustomCodeAvailable($variable->company_id, $code, $variable->id);
            }
            $variable->code = $code;
        }

        $variable->fill([
            'label' => $data['label'] ?? $variable->label,
            'value' => $data['value'] ?? $variable->value,
            'description' => array_key_exists('description', $data) ? $data['description'] : $variable->description,
            'is_active' => $data['is_active'] ?? $variable->is_active,
            'sort_order' => $data['sort_order'] ?? $variable->sort_order,
        ]);
        $variable->save();

        return $variable->fresh();
    }

    /** @return Collection<int, PayrollFormulaCustomVariable> */
    private function activeCustomVariables(int $companyId): Collection
    {
        return PayrollFormulaCustomVariable::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    /** @return array<int, array<string, mixed>> */
    private function parametersWithValues(int $companyId): array
    {
        $resolver = \App\Services\Company\CompanyPolicyResolver::for($companyId);
        $out = [];

        foreach (config('payroll_formula_variables.parameters', []) as $key => $def) {
            $default = (string) ($def['default'] ?? '');
            $out[] = array_merge($def, [
                'key' => $key,
                'value' => (string) $resolver->get($key, $default),
            ]);
        }

        return $out;
    }

    private function normalizeCustomCode(string $code): string
    {
        $code = strtolower(trim($code));
        if (! preg_match('/^[a-z][a-z0-9_]{1,47}$/', $code)) {
            throw new InvalidArgumentException('Mã biến chỉ gồm chữ thường, số và _ (2–48 ký tự).');
        }

        if (in_array($code, $this->reservedKeys(), true)) {
            throw new InvalidArgumentException('Mã biến trùng với biến hệ thống: {'.$code.'}');
        }

        return $code;
    }

    private function assertCustomCodeAvailable(int $companyId, string $code, ?int $ignoreId = null): void
    {
        $exists = PayrollFormulaCustomVariable::where('company_id', $companyId)
            ->where('code', $code)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('Mã biến đã tồn tại trong công ty.');
        }
    }

    /** @param  array<string, mixed>  $def */
    private function assertParameterValue(string $key, array $def, mixed $value): void
    {
        $type = $def['type'] ?? 'number';

        if ($type === 'boolean') {
            if (! in_array((string) $value, ['0', '1', 'true', 'false'], true)) {
                throw new InvalidArgumentException("Giá trị boolean không hợp lệ: {$key}");
            }

            return;
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Giá trị số không hợp lệ: {$key}");
        }

        $num = (float) $value;
        $min = $def['min'] ?? null;
        $max = $def['max'] ?? null;
        if ($min !== null && $num < (float) $min) {
            throw new InvalidArgumentException("{$key} nhỏ hơn mức tối thiểu.");
        }
        if ($max !== null && $num > (float) $max) {
            throw new InvalidArgumentException("{$key} lớn hơn mức tối đa.");
        }
    }

    /** @param  array<string, mixed>  $def */
    private function normalizeStoredValue(array $def, mixed $value): string
    {
        if (($def['type'] ?? '') === 'boolean') {
            return in_array((string) $value, ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
        }

        return (string) $value;
    }
}
