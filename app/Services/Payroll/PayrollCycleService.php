<?php

namespace App\Services\Payroll;

use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\PayrollCycle;
use App\Models\PayrollResult;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayrollCycleService
{
    public function __construct(
        protected VietnamPayrollCalculator $calculator,
        protected PayrollEarningsService $earnings,
        protected PayrollContextBuilder $contextBuilder,
        protected PayrollFormulaService $formulaService,
        protected PayrollFormulaVariableService $formulaVariables,
        protected EmployeePayrollAllowanceService $allowanceService,
        protected PayrollPreviousMonthService $previousMonthService,
    ) {}

    public function calculate(PayrollCycle $cycle): PayrollCycle
    {
        if (in_array($cycle->status, ['approved', 'locked'], true)) {
            throw new RuntimeException('Kỳ lương đã khóa hoặc duyệt — không thể tính lại.');
        }

        $companyId = $cycle->company_id;
        $period = $cycle->period;

        $summaries = AttendanceSummary::query()
            ->where('company_id', $companyId)
            ->where('period', $period)
            ->get();

        if ($summaries->isEmpty()) {
            throw new RuntimeException('Attendance summaries must be built and locked before payroll calculation.');
        }

        $unlocked = $summaries->where('is_locked', false)->count();
        if ($unlocked > 0) {
            throw new RuntimeException('Attendance summaries must be built and locked before payroll calculation.');
        }

        DB::transaction(function () use ($cycle, $companyId, $period, $summaries) {
            PayrollResult::where('payroll_cycle_id', $cycle->id)->delete();

            foreach ($summaries as $summary) {
                $employee = Employee::find($summary->employee_id);
                if (! $employee) {
                    continue;
                }

                $contract = EmploymentContract::where('employee_id', $employee->id)
                    ->where('status', 'active')
                    ->orderByDesc('start_date')
                    ->first();

                if (! $contract) {
                    continue;
                }

                $earned = $this->earnings->calculateGross($employee, $contract, $summary, $period);
                $allowanceMerge = $this->allowanceService->mergeForPayroll(
                    $employee->id,
                    $companyId,
                    $period,
                    $summary,
                );
                $context = $this->contextBuilder->build($employee, $contract, $summary, $period, $earned);
                $diligenceMeta = is_array($summary->attendance_breakdown['diligence'] ?? null)
                    ? $summary->attendance_breakdown['diligence']
                    : [];
                $context['diligence_bonus_amount'] = (float) ($summary->diligence_bonus_amount ?? 0);
                $context['diligence_probation_pay'] = (float) ($diligenceMeta['probation_pay'] ?? 0);
                $context['diligence_official_pay'] = (float) ($diligenceMeta['official_pay'] ?? 0);
                $context = array_merge($context, $allowanceMerge['fields']);
                $context = $this->formulaVariables->enrichContext($context, $companyId);

                $formulas = $this->formulaService->apply($companyId, $context);

                $prevMonthBonus = $this->previousMonthService->resolveBonus(
                    $employee->id,
                    $companyId,
                    $period,
                    $earned['breakdown'],
                );
                $formulaPerformanceBonus = 0.0;
                foreach ($formulas['lines'] as $line) {
                    if (($line['target_field'] ?? '') === 'performance_bonus') {
                        $formulaPerformanceBonus += (float) ($line['amount'] ?? 0);
                    }
                }

                $performanceBonus = $prevMonthBonus['performance_bonus'];
                $prevAdjustment = (float) ($allowanceMerge['fields']['prev_month_adjustment'] ?? 0);
                $bonusDelta = $performanceBonus - $formulaPerformanceBonus;

                $gross = (float) $earned['gross_salary']
                    + $formulas['additional_earnings']
                    + $allowanceMerge['taxable_total']
                    + $bonusDelta;
                $dependents = $employee->pitDependentsForPayroll();

                // HĐTV riêng hoặc HĐ < 3 tháng: khấu trừ thuế TNCN 10% flat (TT 111/2013 Điều 25.1.i)
                // HĐLĐ ≥ 3 tháng: tính thuế theo biểu lũy tiến
                $isProbationContract = $contract->contract_type === 'probation';
                $isShortContract = $isProbationContract
                    || ($contract->contract_duration_months > 0 && $contract->contract_duration_months < 3);

                if ($isShortContract) {
                    $calc = $this->calculator->calculateFlatPit(
                        $gross,
                        false,
                        0.0,
                        2_000_000,
                        (bool) $employee->union_member,
                        (float) $earned['insurance_salary_base']
                    );
                    // Giữ BHXH/BHYT/BHTN/KPCĐ đã tính từ earnings (nếu HĐLĐ ngắn hạn < 3 tháng)
                    if (! $isProbationContract && $earned['insurance_salary_base'] > 0) {
                        $bhxhBase = min($earned['insurance_salary_base'], (float) config('payroll_vn.bhxh.salary_cap'));

                        $bhxhEmp = round($bhxhBase * (float) config('payroll_vn.bhxh.employee_rate', 0.08), 0);
                        $bhytEmp = round($bhxhBase * (float) config('payroll_vn.bhyt.employee_rate', 0.015), 0);
                        $bhtnEmp = round($bhxhBase * (float) config('payroll_vn.bhtn.employee_rate', 0.01), 0);
                        $insuranceTotalEmployee = $bhxhEmp + $bhytEmp + $bhtnEmp;

                        $bhxhEr = round($bhxhBase * (float) config('payroll_vn.bhxh.employer_rate', 0.175), 0);
                        $bhytEr = round($bhxhBase * (float) config('payroll_vn.bhyt.employer_rate', 0.03), 0);
                        $bhtnEr = round($bhxhBase * (float) config('payroll_vn.bhtn.employer_rate', 0.01), 0);
                        $kpcdEr = round($bhxhBase * (float) config('payroll_vn.kpcd.employer_rate', 0.02), 0);
                        $insuranceTotalEmployer = $bhxhEr + $bhytEr + $bhtnEr + $kpcdEr;

                        $calc['bhxh_employee'] = $insuranceTotalEmployee;
                        $calc['bhxh_employer'] = $insuranceTotalEmployer;
                        $calc['net_salary'] = round($gross - $insuranceTotalEmployee - $calc['pit_amount'] - (float) $calc['union_fee'], 0);
                        $calc['insurance_salary_base'] = round($earned['insurance_salary_base'], 0);

                        $calc['bhxh_employee_detail'] = $bhxhEmp;
                        $calc['bhyt_employee_detail'] = $bhytEmp;
                        $calc['bhtn_employee_detail'] = $bhtnEmp;
                        $calc['bhxh_employer_detail'] = $bhxhEr;
                        $calc['bhyt_employer_detail'] = $bhytEr;
                        $calc['bhtn_employer_detail'] = $bhtnEr;
                        $calc['kpcd_employer_detail'] = $kpcdEr;
                    }
                } else {
                    $calc = $this->calculator->calculateWithInsuranceBase(
                        $gross,
                        $earned['insurance_salary_base'],
                        $dependents,
                        0.0,
                        0.0,
                        (bool) $employee->union_member
                    );
                }

                $otherDeductions = (float) $calc['other_deductions'] + $formulas['additional_deductions'];
                $net = (float) $calc['net_salary'] - $formulas['additional_deductions'];

                $attendanceBreakdown = is_array($summary->attendance_breakdown)
                    ? $summary->attendance_breakdown
                    : [];

                $breakdown = array_merge($calc, $earned['breakdown'], [
                    'gross_before_formulas' => $earned['gross_salary'],
                    'formula_earnings' => $formulas['additional_earnings'],
                    'formula_deductions' => $formulas['additional_deductions'],
                    'formula_lines' => $formulas['lines'],
                    'allowance_earnings' => $allowanceMerge['taxable_total'],
                    'allowance_non_taxable' => $allowanceMerge['non_taxable_total'],
                    'phased_allowances' => $allowanceMerge['phased_allowances'] ?? [],
                    'diligence_probation_pay' => (float) ($diligenceMeta['probation_pay'] ?? 0),
                    'diligence_official_pay' => (float) ($diligenceMeta['official_pay'] ?? 0),
                    'diligence_phase_mode' => $diligenceMeta['phase_mode'] ?? null,
                    'final_gross' => $gross,
                    'attendance_breakdown' => $attendanceBreakdown,
                    'ot_grid' => $attendanceBreakdown['ot'] ?? [],
                    'leave_by_type' => $attendanceBreakdown['leave_by_type'] ?? [],
                ]);

                $breakdown = array_merge($breakdown, $allowanceMerge['fields']);

                if (! empty($attendanceBreakdown['work']) && is_array($attendanceBreakdown['work'])) {
                    $breakdown = array_merge($breakdown, $attendanceBreakdown['work']);
                }

                $breakdown['night_hours'] = (float) ($summary->night_hours ?? 0);

                foreach ($formulas['lines'] as $line) {
                    if (($line['amount'] ?? 0) > 0 && ! empty($line['target_field'])) {
                        if ($line['target_field'] === 'performance_bonus') {
                            continue;
                        }
                        $breakdown[$line['target_field']] = $line['amount'];
                    }
                }

                $breakdown['performance_bonus'] = $performanceBonus;
                $breakdown['performance_bonus_probation'] = (float) ($prevMonthBonus['performance_bonus_probation'] ?? 0);
                $breakdown['performance_bonus_official'] = (float) ($prevMonthBonus['performance_bonus_official'] ?? 0);
                $breakdown['performance_bonus_split'] = (bool) ($prevMonthBonus['performance_bonus_split'] ?? false);
                $breakdown['prev_month_adjustment'] = $prevAdjustment;
                $breakdown['prev_month_base_pay'] = $prevMonthBonus['prev_month_base_pay'];
                $breakdown['prev_month_probation_base_pay'] = (float) ($prevMonthBonus['prev_month_probation_base_pay'] ?? 0);
                $breakdown['prev_month_official_base_pay'] = (float) ($prevMonthBonus['prev_month_official_base_pay'] ?? 0);
                $breakdown['prev_month_period'] = $prevMonthBonus['prev_month_period'];
                $breakdown['performance_score_prev_month'] = $prevMonthBonus['performance_score'];

                $earnedBd = $earned['breakdown'];
                $payableProbation = (float) ($earnedBd['payable_probation_days'] ?? 0);
                $payableOfficial = (float) ($earnedBd['payable_official_days'] ?? 0);
                $breakdown['payslip_attendance'] = [
                    'standard_work_days' => (float) ($earnedBd['standard_work_days'] ?? $summary->standard_work_days),
                    'work_days' => (float) ($earnedBd['work_days'] ?? $summary->work_days),
                    'probation_work_days' => (float) ($earnedBd['probation_work_days'] ?? $summary->probation_work_days),
                    'official_work_days' => (float) ($earnedBd['official_work_days'] ?? $summary->official_work_days),
                    'payable_probation_days' => $payableProbation,
                    'payable_official_days' => $payableOfficial,
                    'payable_total_days' => $payableProbation + $payableOfficial,
                    'paid_leave_days' => (float) ($earnedBd['paid_leave_days'] ?? $summary->paid_leave_days),
                    'unpaid_leave_days' => (float) ($earnedBd['unpaid_leave_days'] ?? $summary->unpaid_leave_days),
                    'absent_days' => (float) ($earnedBd['absent_days'] ?? $summary->absent_days),
                    'has_phase_split' => (bool) ($earnedBd['has_phase_split'] ?? false),
                ];

                PayrollResult::create([
                    'payroll_cycle_id' => $cycle->id,
                    'employee_id' => $employee->id,
                    'gross_salary' => $gross,
                    'bhxh_employee' => $calc['bhxh_employee'],
                    'bhxh_employer' => $calc['bhxh_employer'],
                    'pit_amount' => $calc['pit_amount'],
                    'other_deductions' => $otherDeductions,
                    'net_salary' => max(0, $net),
                    'breakdown' => $breakdown,
                ]);
            }

            $cycle->update([
                'status' => 'calculated',
                'calculated_at' => now(),
            ]);
        });

        return $cycle->fresh(['results.employee']);
    }
}
