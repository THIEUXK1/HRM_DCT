<?php

namespace App\Services\Payroll;

use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Models\EmployeePayrollAllowance;
use App\Services\Payroll\PhasedIncomeCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeePayrollAllowanceService
{
    public function __construct(
        private readonly PhasedIncomeCalculator $phasedCalculator,
    ) {}

    /** @return array<string, mixed> */
    public function catalog(): array
    {
        return [
            'items' => config('payroll_allowances.catalog', []),
            'travel_support_key' => config('payroll_allowances.travel_support_key', 'travel_support'),
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, period: string, catalog: array<string, mixed>}
     */
    public function listForPeriod(int $companyId, string $period): array
    {
        $sheets = EmployeePayrollAllowance::with('employee:id,employee_code,full_name,department_id', 'employee.department:id,name')
            ->where('company_id', $companyId)
            ->where('period', $period)
            ->get()
            ->keyBy('employee_id');

        $employees = Employee::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('employee_code')
            ->get(['id', 'employee_code', 'full_name', 'department_id']);

        $summaries = AttendanceSummary::where('company_id', $companyId)
            ->where('period', $period)
            ->get()
            ->keyBy('employee_id');

        $rows = [];
        foreach ($employees as $employee) {
            $sheet = $sheets->get($employee->id);
            $summary = $summaries->get($employee->id);
            $rows[] = $this->formatRow($employee, $sheet, $summary, $companyId, $period);
        }

        return [
            'period' => $period,
            'catalog' => $this->catalog(),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsert(int $companyId, array $payload): EmployeePayrollAllowance
    {
        $employeeId = (int) $payload['employee_id'];
        $period = (string) $payload['period'];

        app(\App\Services\Payroll\PayrollCycleLockService::class)->assertNotLocked($companyId, $period);

        $employee = Employee::where('company_id', $companyId)->findOrFail($employeeId);

        $allowances = $this->normalizeAllowances($payload['allowances'] ?? [], $period);

        return EmployeePayrollAllowance::updateOrCreate(
            ['employee_id' => $employee->id, 'period' => $period],
            [
                'company_id' => $companyId,
                'allowances' => $allowances,
                'travel_support_amount' => max(0, (float) ($payload['travel_support_amount'] ?? 0)),
                'travel_eligible' => (bool) ($payload['travel_eligible'] ?? false),
                'prev_month_adjustment' => (float) ($payload['prev_month_adjustment'] ?? 0),
                'notes' => $payload['notes'] ?? null,
            ],
        );
    }

    public function copyFromPreviousPeriod(int $companyId, string $period): int
    {
        $prev = Carbon::createFromFormat('!Y-m-d', $period.'-01')->subMonth()->format('Y-m');

        $previousSheets = EmployeePayrollAllowance::where('company_id', $companyId)
            ->where('period', $prev)
            ->get();

        if ($previousSheets->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($previousSheets, $companyId, $period) {
            $count = 0;
            foreach ($previousSheets as $sheet) {
                EmployeePayrollAllowance::updateOrCreate(
                    ['employee_id' => $sheet->employee_id, 'period' => $period],
                    [
                        'company_id' => $companyId,
                        'allowances' => $sheet->allowances,
                        'travel_support_amount' => $sheet->travel_support_amount,
                        'travel_eligible' => $sheet->travel_eligible,
                        'notes' => $sheet->notes,
                    ],
                );
                $count++;
            }

            return $count;
        });
    }

    /**
     * Gộp trợ cấp tháng vào breakdown + tính thêm gross chịu thuế.
     *
     * @return array{
     *   fields: array<string, float|bool>,
     *   taxable_total: float,
     *   non_taxable_total: float,
     *   sheet_id: int|null,
     *   phased_allowances: array<string, array{total: float, probation: float, official: float, mode: string}>
     * }
     */
    public function mergeForPayroll(
        int $employeeId,
        int $companyId,
        string $period,
        ?AttendanceSummary $summary = null,
    ): array {
        $sheet = EmployeePayrollAllowance::where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('period', $period)
            ->first();

        if (! $sheet) {
            return [
                'fields' => [],
                'taxable_total' => 0.0,
                'non_taxable_total' => 0.0,
                'sheet_id' => null,
                'phased_allowances' => [],
            ];
        }

        $catalog = config('payroll_allowances.catalog', []);
        $fields = [];
        $phasedAllowances = [];
        $taxableTotal = 0.0;
        $nonTaxableTotal = 0.0;
        $month = (int) Carbon::createFromFormat('Y-m', $period)->format('m');
        $probationDays = (float) ($summary?->probation_work_days ?? 0);
        $officialDays = (float) ($summary?->official_work_days ?? 0);
        $totalWorkDays = (float) ($summary?->work_days ?? 0);
        if ($probationDays <= 0 && $officialDays <= 0 && $totalWorkDays > 0) {
            $officialDays = $totalWorkDays;
        }
        $standardDays = max(1.0, (float) ($summary?->standard_work_days ?? 0));
        $hasPhaseSplit = $probationDays > 0 && $officialDays > 0;

        foreach ($sheet->allowances ?? [] as $code => $rawAmount) {
            if (! isset($catalog[$code])) {
                continue;
            }

            $meta = $catalog[$code];
            if (! $this->isActiveForMonth($meta, $month)) {
                continue;
            }

            $monthlyAmount = max(0, (float) $rawAmount);
            if ($monthlyAmount <= 0) {
                continue;
            }

            $target = (string) ($meta['breakdown_key'] ?? $code);
            $mode = $this->phasedCalculator->resolveAllowanceMode($code);
            $split = $hasPhaseSplit
                ? $this->phasedCalculator->calculateMonthly(
                    $monthlyAmount,
                    $monthlyAmount,
                    $probationDays,
                    $officialDays,
                    $standardDays,
                    $mode,
                    true,
                )
                : ['total' => $monthlyAmount, 'probation' => 0.0, 'official' => $monthlyAmount, 'mode' => $mode];

            $amount = (float) $split['total'];
            $fields[$target] = $amount;
            if ($hasPhaseSplit && ($split['probation'] > 0 || $split['official'] > 0)) {
                $fields[$target.'_probation'] = (float) $split['probation'];
                $fields[$target.'_official'] = (float) $split['official'];
                $phasedAllowances[$target] = [
                    'total' => $amount,
                    'probation' => (float) $split['probation'],
                    'official' => (float) $split['official'],
                    'mode' => (string) $split['mode'],
                ];
            }

            if (! ($meta['counts_in_gross'] ?? true)) {
                $nonTaxableTotal += $amount;
                continue;
            }

            if ($meta['taxable'] ?? true) {
                $taxableTotal += $amount;
            } else {
                $nonTaxableTotal += $amount;
            }
        }

        if ((float) $sheet->travel_support_amount > 0) {
            $travelKey = config('payroll_allowances.travel_support_key', 'travel_support');
            $fields[$travelKey] = (float) $sheet->travel_support_amount;
            $taxableTotal += (float) $sheet->travel_support_amount;
        }

        if ($sheet->travel_eligible) {
            $fields['travel_eligible'] = true;
        }

        $prevAdjustment = (float) ($sheet->prev_month_adjustment ?? 0);
        if ($prevAdjustment != 0.0) {
            $fields['prev_month_adjustment'] = $prevAdjustment;
            $taxableTotal += $prevAdjustment;
        }

        return [
            'fields' => $fields,
            'taxable_total' => round($taxableTotal, 0),
            'non_taxable_total' => round($nonTaxableTotal, 0),
            'sheet_id' => $sheet->id,
            'phased_allowances' => $phasedAllowances,
        ];
    }

    /** @param  array<string, mixed>  $meta */
    private function isActiveForMonth(array $meta, int $month): bool
    {
        $activeMonths = $meta['active_months'] ?? null;
        if ($activeMonths === null) {
            return true;
        }

        return in_array($month, $activeMonths, true);
    }

    /** @param  array<string, mixed>  $input */
    private function normalizeAllowances(array $input, string $period): array
    {
        $catalog = config('payroll_allowances.catalog', []);
        $month = (int) Carbon::createFromFormat('Y-m', $period)->format('m');
        $normalized = [];

        foreach ($catalog as $code => $meta) {
            if (! array_key_exists($code, $input)) {
                continue;
            }
            if (! $this->isActiveForMonth($meta, $month)) {
                $normalized[$code] = 0;
                continue;
            }
            $normalized[$code] = max(0, (float) $input[$code]);
        }

        return $normalized;
    }

    /** @return array<string, mixed> */
    private function formatRow(
        Employee $employee,
        ?EmployeePayrollAllowance $sheet,
        ?AttendanceSummary $summary = null,
        ?int $companyId = null,
        ?string $period = null,
    ): array {
        $phasedPreview = null;
        if ($summary && $companyId && $period) {
            $merge = $this->mergeForPayroll($employee->id, $companyId, $period, $summary);
            $phasedPreview = [
                'has_phase_split' => (float) ($summary->probation_work_days ?? 0) > 0
                    && (float) ($summary->official_work_days ?? 0) > 0,
                'probation_work_days' => (float) ($summary->probation_work_days ?? 0),
                'official_work_days' => (float) ($summary->official_work_days ?? 0),
                'standard_work_days' => (float) ($summary->standard_work_days ?? 0),
                'items' => $merge['phased_allowances'] ?? [],
                'payroll_totals' => [
                    'taxable' => $merge['taxable_total'],
                    'non_taxable' => $merge['non_taxable_total'],
                ],
            ];
        }

        return [
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'full_name' => $employee->full_name,
            'department' => $employee->department?->name,
            'sheet_id' => $sheet?->id,
            'allowances' => $sheet?->allowances ?? [],
            'travel_support_amount' => (float) ($sheet?->travel_support_amount ?? 0),
            'travel_eligible' => (bool) ($sheet?->travel_eligible ?? false),
            'prev_month_adjustment' => (float) ($sheet?->prev_month_adjustment ?? 0),
            'notes' => $sheet?->notes,
            'total_allowances' => $this->sumAllowances($sheet),
            'phased_preview' => $phasedPreview,
        ];
    }

    private function sumAllowances(?EmployeePayrollAllowance $sheet): float
    {
        if (! $sheet) {
            return 0.0;
        }

        $total = array_sum(array_map('floatval', $sheet->allowances ?? []));
        $total += (float) $sheet->travel_support_amount;

        return round($total, 0);
    }
}
