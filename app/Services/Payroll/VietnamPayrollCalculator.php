<?php

namespace App\Services\Payroll;

class VietnamPayrollCalculator
{
    public function calculate(
        float $grossSalary,
        int $dependents = 0,
        float $otherDeductions = 0,
        float $otherTaxableIncome = 0
    ): array {
        $gross = $grossSalary + $otherTaxableIncome;
        $bhxhBase = min($gross, (float) config('payroll_vn.bhxh.salary_cap'));
        $bhxhEmployee = round($bhxhBase * config('payroll_vn.bhxh.employee_rate'), 0);
        $bhxhEmployer = round($bhxhBase * config('payroll_vn.bhxh.employer_rate'), 0);

        $personalDeduction = (int) config('payroll_vn.pit.personal_deduction');
        $dependentDeduction = (int) config('payroll_vn.pit.dependent_deduction') * $dependents;
        $taxableIncome = max(0, $gross - $bhxhEmployee - $personalDeduction - $dependentDeduction);
        $pit = $this->calculateProgressivePit($taxableIncome);
        $net = $gross - $bhxhEmployee - $pit - $otherDeductions;

        return [
            'gross_salary' => round($gross, 0),
            'bhxh_employee' => $bhxhEmployee,
            'bhxh_employer' => $bhxhEmployer,
            'pit_amount' => $pit,
            'other_deductions' => round($otherDeductions, 0),
            'net_salary' => round($net, 0),
            'taxable_income' => round($taxableIncome, 0),
        ];
    }

    /**
     * Tính lương khi mức đóng BHXH (insurance base) khác tổng thu nhập chịu thuế (gross).
     */
    public function calculateWithInsuranceBase(
        float $grossSalary,
        float $insuranceSalaryBase,
        int $dependents = 0,
        float $otherDeductions = 0,
        float $otherTaxableIncome = 0,
        bool $unionMember = false
    ): array {
        $gross = $grossSalary + $otherTaxableIncome;
        $insuranceCap = (float) config('payroll_vn.bhxh.salary_cap');
        $bhxhBase = min($insuranceSalaryBase, $insuranceCap);

        // Employee insurance rates
        $bhxhEmpRate = (float) config('payroll_vn.bhxh.employee_rate', 0.08);
        $bhytEmpRate = (float) config('payroll_vn.bhyt.employee_rate', 0.015);
        $bhtnEmpRate = (float) config('payroll_vn.bhtn.employee_rate', 0.01);

        // Employer insurance rates
        $bhxhErRate = (float) config('payroll_vn.bhxh.employer_rate', 0.175);
        $bhytErRate = (float) config('payroll_vn.bhyt.employer_rate', 0.03);
        $bhtnErRate = (float) config('payroll_vn.bhtn.employer_rate', 0.01);
        $kpcdErRate = (float) config('payroll_vn.kpcd.employer_rate', 0.02);

        // Detailed Employee Deductions
        $bhxhEmployee = round($bhxhBase * $bhxhEmpRate, 0);
        $bhytEmployee = round($bhxhBase * $bhytEmpRate, 0);
        $bhtnEmployee = round($bhxhBase * $bhtnEmpRate, 0);
        $insuranceTotalEmployee = $bhxhEmployee + $bhytEmployee + $bhtnEmployee;

        // Detailed Employer Contributions
        $bhxhEmployer = round($bhxhBase * $bhxhErRate, 0);
        $bhytEmployer = round($bhxhBase * $bhytErRate, 0);
        $bhtnEmployer = round($bhxhBase * $bhtnErRate, 0);
        $kpcdEmployer = round($bhxhBase * $kpcdErRate, 0);
        $insuranceTotalEmployer = $bhxhEmployer + $bhytEmployer + $bhtnEmployer + $kpcdEmployer;

        // Union Member Fee (1% deducted from employee net salary, capped)
        $unionFee = 0.0;
        if ($unionMember) {
            $unionFeeRate = (float) config('payroll_vn.union_fee.employee_rate', 0.01);
            $unionFeeCap = (float) config('payroll_vn.union_fee.cap_amount', 180000);
            $unionFee = min(round($insuranceSalaryBase * $unionFeeRate, 0), $unionFeeCap);
        }

        $personalDeduction = (int) config('payroll_vn.pit.personal_deduction');
        $dependentDeduction = (int) config('payroll_vn.pit.dependent_deduction') * $dependents;
        $taxableIncome = max(0, $gross - $insuranceTotalEmployee - $personalDeduction - $dependentDeduction);
        $pit = $this->calculateProgressivePit($taxableIncome);
        $net = $gross - $insuranceTotalEmployee - $pit - $otherDeductions - $unionFee;

        return [
            'gross_salary' => round($gross, 0),
            'bhxh_employee' => $insuranceTotalEmployee, // Combined for payslip
            'bhxh_employer' => $insuranceTotalEmployer, // Combined for company cost
            'pit_amount' => $pit,
            'other_deductions' => round($otherDeductions, 0),
            'net_salary' => round($net, 0),
            'taxable_income' => round($taxableIncome, 0),
            'insurance_salary_base' => round($insuranceSalaryBase, 0),
            'pit_dependents' => $dependents,

            // Detailed splits
            'bhxh_employee_detail' => $bhxhEmployee,
            'bhyt_employee_detail' => $bhytEmployee,
            'bhtn_employee_detail' => $bhtnEmployee,
            'bhxh_employer_detail' => $bhxhEmployer,
            'bhyt_employer_detail' => $bhytEmployer,
            'bhtn_employer_detail' => $bhtnEmployer,
            'kpcd_employer_detail' => $kpcdEmployer,
            'union_fee' => $unionFee,
            'union_member' => $unionMember,
        ];
    }

    /**
     * Khấu trừ 10% thuế TNCN cho HĐTV riêng hoặc HĐ < 3 tháng (Thông tư 111/2013 Điều 25.1.i).
     * Không áp dụng nếu NLĐ có cam kết thu nhập chưa đến ngưỡng chịu thuế.
     */
    public function calculateFlatPit(
        float $grossSalary,
        bool $hasCommitment = false,
        float $otherDeductions = 0,
        float $commitmentThreshold = 2_000_000,
        bool $unionMember = false,
        float $insuranceSalaryBase = 0.0
    ): array {
        $pit = 0.0;
        if (! $hasCommitment && $grossSalary >= $commitmentThreshold) {
            $pit = round($grossSalary * 0.10, 0);
        }

        // Union Member Fee
        $unionFee = 0.0;
        if ($unionMember && $insuranceSalaryBase > 0) {
            $unionFeeRate = (float) config('payroll_vn.union_fee.employee_rate', 0.01);
            $unionFeeCap = (float) config('payroll_vn.union_fee.cap_amount', 180000);
            $unionFee = min(round($insuranceSalaryBase * $unionFeeRate, 0), $unionFeeCap);
        }

        $net = $grossSalary - $pit - $otherDeductions - $unionFee;

        return [
            'gross_salary' => round($grossSalary, 0),
            'bhxh_employee' => 0.0,
            'bhxh_employer' => 0.0,
            'pit_amount' => $pit,
            'pit_method' => 'flat_10',
            'other_deductions' => round($otherDeductions, 0),
            'net_salary' => round($net, 0),
            'taxable_income' => round($grossSalary, 0),
            'insurance_salary_base' => 0.0,
            'pit_dependents' => 0,

            // Detailed splits (all zero or default)
            'bhxh_employee_detail' => 0.0,
            'bhyt_employee_detail' => 0.0,
            'bhtn_employee_detail' => 0.0,
            'bhxh_employer_detail' => 0.0,
            'bhyt_employer_detail' => 0.0,
            'bhtn_employer_detail' => 0.0,
            'kpcd_employer_detail' => 0.0,
            'union_fee' => $unionFee,
            'union_member' => $unionMember,
        ];
    }

    public function calculateProgressivePit(float $taxableIncome): float
    {
        if ($taxableIncome <= 0) {
            return 0;
        }

        $brackets = config('payroll_vn.pit.brackets');
        $previousCap = 0;

        foreach ($brackets as $bracket) {
            $cap = $bracket['up_to'];
            if ($taxableIncome <= $cap) {
                return round($taxableIncome * $bracket['rate'] - $bracket['quick_deduction'], 0);
            }
            $previousCap = $cap;
        }

        $last = end($brackets);

        return round($taxableIncome * $last['rate'] - $last['quick_deduction'], 0);
    }
}
