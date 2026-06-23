<?php

namespace App\Services\Bhxh;

class BhxhContributionCalculator
{
    public function forSalary(float $insuranceSalary): array
    {
        $base = min(
            max($insuranceSalary, (float) config('bhxh_vn.salary.min_base')),
            (float) config('bhxh_vn.salary.max_base')
        );

        $rates = config('bhxh_vn.rates');

        $bhxhEmp = round($base * $rates['bhxh']['employee'], 0);
        $bhxhEr = round($base * $rates['bhxh']['employer'], 0);
        $bhytEmp = round($base * $rates['bhyt']['employee'], 0);
        $bhytEr = round($base * $rates['bhyt']['employer'], 0);
        $bhtnEmp = round($base * $rates['bhtn']['employee'], 0);
        $bhtnEr = round($base * $rates['bhtn']['employer'], 0);
        $kpcdEr = round($base * $rates['kpcd']['employer'], 0);

        $employeeTotal = $bhxhEmp + $bhytEmp + $bhtnEmp;
        $employerTotal = $bhxhEr + $bhytEr + $bhtnEr + $kpcdEr;

        return [
            'insurance_base' => round($base, 0),
            'bhxh_employee' => $bhxhEmp,
            'bhxh_employer' => $bhxhEr,
            'bhyt_employee' => $bhytEmp,
            'bhyt_employer' => $bhytEr,
            'bhtn_employee' => $bhtnEmp,
            'bhtn_employer' => $bhtnEr,
            'kpcd_employer' => $kpcdEr,
            'employee_total' => $employeeTotal,
            'employer_total' => $employerTotal,
            'grand_total' => $employeeTotal + $employerTotal,
        ];
    }
}
