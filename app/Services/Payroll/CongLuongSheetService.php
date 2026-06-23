<?php

namespace App\Services\Payroll;

use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Models\EmployeePayrollAllowance;
use App\Models\EmploymentContract;
use App\Models\PayrollCycle;
use App\Models\PayrollResult;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Map attendance + trợ cấp → dòng bảng « công và lương » BestPacific (1 NV = 1 dòng).
 */
class CongLuongSheetService
{
    /** @return array{period: string, template: string, cong: array{columns: array, rows: list<array>}, luong: array{columns: array, rows: list<array>}} */
    public function report(int $companyId, string $period, ?int $departmentId = null, ?int $branchId = null): array
    {
        $summaries = AttendanceSummary::with(['employee.department:id,name'])
            ->where('company_id', $companyId)
            ->where('period', $period)
            ->when($departmentId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $departmentId)))
            ->when($branchId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $branchId)))
            ->get()
            ->sortBy(fn (AttendanceSummary $s) => $s->employee?->employee_code ?? '');

        $allowances = EmployeePayrollAllowance::where('company_id', $companyId)
            ->where('period', $period)
            ->get()
            ->keyBy('employee_id');

        $payrollResults = $this->payrollResultsForPeriod($companyId, $period);

        $congRows = [];
        $luongRows = [];
        $stt = 0;

        foreach ($summaries as $summary) {
            $employee = $summary->employee;
            if (! $employee) {
                continue;
            }
            $stt++;
            $congRows[] = $this->buildCongRow($stt, $summary, $employee);
            $luongRows[] = $this->buildLuongRow(
                $stt,
                $employee,
                $allowances->get($employee->id),
                $payrollResults->get($employee->id),
                $summary,
            );
        }

        return [
            'period' => $period,
            'template' => 'cong-va-luong-mau.xlsx',
            'reference_period' => config('cong_luong_sheet.reference_period'),
            'cong' => [
                'columns' => $this->columnMeta('cong_columns'),
                'rows' => $congRows,
            ],
            'luong' => [
                'columns' => $this->columnMeta('luong_columns'),
                'rows' => $luongRows,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function buildCongRow(int $stt, AttendanceSummary $summary, Employee $employee): array
    {
        $breakdown = is_array($summary->attendance_breakdown) ? $summary->attendance_breakdown : [];
        $work = $breakdown['work'] ?? [];
        $meta = $breakdown['meta'] ?? [];
        $ot = $breakdown['ot'] ?? [];
        $leave = $breakdown['leave_by_type'] ?? [];

        $employmentStatus = (string) ($meta['employment_status_label']
            ?? $this->statusLabelFromCode((string) ($meta['employment_status'] ?? 'official')));

        $joinDate = $meta['join_date_in_period']
            ?? $work['join_date']
            ?? ($employee->hire_date?->format('Y-m-d'));

        if ($joinDate) {
            $joinDate = $this->formatJoinDateDisplay($joinDate);
        }

        $activeLabel = (string) ($meta['employment_active_label']
            ?? ($employee->is_active
                ? config('cong_luong_sheet.employment_active_labels.active')
                : config('cong_luong_sheet.employment_active_labels.inactive')));

        $travelFlag = $work['travel_support'] ?? null;

        return [
            'stt' => $stt,
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'full_name' => $employee->full_name,
            'department' => $employee->department?->name ?? '',
            'job_level' => $meta['job_level'] ?? $this->resolveJobLevel($employee),
            'payable_work_days' => (float) ($work['payable_work_days'] ?? $summary->work_days),
            'paid_holiday_leave_days' => (float) ($work['paid_holiday_leave_days'] ?? 0),
            'minimum_wage_days' => (float) ($work['minimum_wage_days'] ?? 0),
            'base_salary_paid_leave_days' => (float) ($work['base_salary_paid_leave_days'] ?? $summary->paid_leave_days),
            'holiday_days' => (float) ($work['holiday_days'] ?? 0),
            'night_hours_summary' => (float) ($work['night_hours_summary'] ?? $summary->night_hours),
            'business_trip_days' => (float) ($work['business_trip_days'] ?? 0),
            'menstrual_leave_hours' => (float) ($work['menstrual_leave_hours'] ?? 0),
            'ot_night_weekday' => (float) ($ot['night_weekday'] ?? 0),
            'ot_night_weekend' => (float) ($ot['night_weekend'] ?? 0),
            'ot_night_paid_holiday' => (float) ($ot['night_paid_holiday'] ?? 0),
            'ot_day_weekday' => (float) ($ot['day_weekday'] ?? 0),
            'ot_day_weekend' => (float) ($ot['day_weekend'] ?? 0),
            'ot_day_annual_leave' => (float) ($ot['day_annual_leave'] ?? 0),
            'ot_night_annual_leave' => (float) ($ot['night_annual_leave'] ?? 0),
            'ot_day_holiday' => (float) ($ot['day_holiday'] ?? 0),
            'ot_night_holiday' => (float) ($ot['night_holiday'] ?? 0),
            'leave_annual' => (float) ($leave['annual'] ?? 0),
            'leave_personal' => (float) ($leave['personal'] ?? 0),
            'leave_wedding' => (float) ($leave['wedding'] ?? 0),
            'leave_maternity' => (float) ($leave['maternity'] ?? 0),
            'leave_funeral' => (float) ($leave['funeral'] ?? 0),
            'leave_sick' => (float) ($leave['sick'] ?? 0),
            'leave_unauthorized' => (float) ($leave['unauthorized'] ?? 0),
            'leave_company' => (float) ($leave['company'] ?? 0),
            'travel_support_flag' => ($travelFlag === true || $travelFlag === 'Có') ? 'Có' : '-',
            'saturday_duty_hours' => (float) ($work['saturday_duty_hours'] ?? 0),
            'resignation_note' => (string) ($meta['resignation_note'] ?? $work['resignation_note'] ?? ''),
            'resignation_days' => (float) ($work['resignation_days'] ?? 0),
            'days_not_joined' => (float) ($work['days_not_joined'] ?? 0),
            'join_date' => $joinDate,
            'standard_work_days' => (float) ($work['standard_work_days'] ?? $summary->standard_work_days),
            'employment_status' => $employmentStatus,
            'employment_active' => $activeLabel,
            'is_locked' => (bool) $summary->is_locked,
        ];
    }

    /** @return array<string, mixed> */
    public function buildLuongRow(
        int $stt,
        Employee $employee,
        ?EmployeePayrollAllowance $sheet,
        ?PayrollResult $result,
        ?AttendanceSummary $summary = null,
    ): array {
        $allowances = $sheet?->allowances ?? [];
        $contractSalary = $this->activeBaseSalary($employee);
        $meta = is_array($summary?->attendance_breakdown)
            ? ($summary->attendance_breakdown['meta'] ?? [])
            : [];

        $row = [
            'stt' => $stt,
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'full_name' => $employee->full_name,
            'department' => (string) ($meta['department_name'] ?? $employee->department?->name ?? ''),
            'job_level' => $meta['job_level'] ?? $this->resolveJobLevel($employee),
            'base_salary' => (float) ($contractSalary ?? 0),
            'travel_support_amount' => (float) ($sheet?->travel_support_amount ?? 0),
            'travel_eligible' => ($sheet?->travel_eligible ?? false) ? 'Có' : '-',
            'notes' => $sheet?->notes ?? '',
        ];

        foreach (config('cong_luong_import.luong_allowance_columns', []) as $col => $catalogCode) {
            $row[$catalogCode] = (float) ($allowances[$catalogCode] ?? 0);
        }

        return $row;
    }

    /** @return list<array{col: string, key: string, label: string, align: string, sticky: bool, numeric: bool}> */
    public function columnMeta(string $configKey): array
    {
        $columns = [];
        foreach (config("cong_luong_sheet.{$configKey}", []) as $col => $meta) {
            $columns[] = [
                'col' => $col,
                'key' => $meta['key'],
                'label' => (string) ($meta['label_vi'] ?? $meta['label'] ?? $col),
                'align' => (string) ($meta['align'] ?? 'left'),
                'sticky' => (bool) ($meta['sticky'] ?? false),
                'numeric' => (bool) ($meta['numeric'] ?? false),
            ];
        }

        return $columns;
    }

    /** @return Collection<int, PayrollResult> */
    private function payrollResultsForPeriod(int $companyId, string $period): Collection
    {
        $cycle = PayrollCycle::where('company_id', $companyId)
            ->where('period', $period)
            ->first();

        if (! $cycle) {
            return collect();
        }

        return PayrollResult::where('payroll_cycle_id', $cycle->id)->get()->keyBy('employee_id');
    }

    private function statusLabelFromCode(string $code): string
    {
        return config("cong_luong_sheet.employment_status_labels.{$code}", 'Chính thức');
    }

    private function formatJoinDateDisplay(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('Y/m/d');
        }

        $parsed = Carbon::parse((string) $value);

        return $parsed->format('Y/m/d');
    }

    private function resolveJobLevel(Employee $employee): int|string
    {
        return $employee->job_level ?? 7;
    }

    private function activeBaseSalary(Employee $employee): ?float
    {
        $contract = EmploymentContract::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->orderByDesc('start_date')
            ->first();

        return $contract ? (float) $contract->salary_base : null;
    }
}
