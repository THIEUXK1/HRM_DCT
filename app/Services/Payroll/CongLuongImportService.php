<?php

namespace App\Services\Payroll;

use App\Models\AttendanceSummary;
use App\Models\Employee;
use App\Services\Attendance\AttendanceWorkDaysPhaseSplitter;
use App\Services\Attendance\EmploymentPhaseResolver;
use App\Services\Export\SimpleXlsxReader;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Import file Excel « công và lương » (2 sheet) — Phase 2d.
 */
class CongLuongImportService
{
    public function __construct(
        private readonly SimpleXlsxReader $reader,
        private readonly EmployeePayrollAllowanceService $allowanceService,
        private readonly AttendanceWorkDaysPhaseSplitter $workDaysSplitter,
        private readonly EmploymentPhaseResolver $phaseResolver,
    ) {}

    /**
     * @return array{
     *   period: string,
     *   cong: array{imported: int, skipped: int, errors: list<string>},
     *   luong: array{imported: int, skipped: int, errors: list<string>}
     * }
     */
    public function import(int $companyId, string $period, UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if (! $path || ! is_readable($path)) {
            throw new RuntimeException('Không đọc được file Excel.');
        }

        $congPath = $this->reader->findSheetPath($path, config('cong_luong_import.sheet_cong.match_names', []));
        $luongPath = $this->reader->findSheetPath($path, config('cong_luong_import.sheet_luong.match_names', []));

        if (! $congPath && ! $luongPath) {
            throw new RuntimeException('File phải có sheet « công » hoặc « lương ».');
        }

        $employees = Employee::where('company_id', $companyId)
            ->get()
            ->keyBy(fn (Employee $e) => strtoupper(trim($e->employee_code)));

        if (app(\App\Services\Attendance\AttendancePeriodLockService::class)->isLocked($companyId, $period)) {
            throw new RuntimeException("Kỳ công {$period} đã khóa — không thể import.");
        }

        return DB::transaction(function () use ($companyId, $period, $path, $congPath, $luongPath, $employees) {
            $congResult = ['imported' => 0, 'skipped' => 0, 'errors' => []];
            $luongResult = ['imported' => 0, 'skipped' => 0, 'errors' => []];

            if ($congPath) {
                $congResult = $this->importCongSheet($companyId, $period, $path, $congPath, $employees);
            }

            if ($luongPath) {
                $luongResult = $this->importLuongSheet($companyId, $period, $path, $luongPath, $employees);
            }

            return [
                'period' => $period,
                'cong' => $congResult,
                'luong' => $luongResult,
            ];
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Employee>  $employees
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    private function importCongSheet(
        int $companyId,
        string $period,
        string $filepath,
        string $sheetPath,
        $employees,
    ): array {
        $cfg = config('cong_luong_import.sheet_cong');
        $rows = $this->reader->readSheetKeyedRows($filepath, $sheetPath, (int) ($cfg['data_start_row'] ?? 4));
        $codeCol = (string) ($cfg['employee_code_column'] ?? 'B');

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $row) {
            $rowNum = (int) ($row['_row'] ?? 0);
            $code = strtoupper(trim((string) ($row[$codeCol] ?? '')));

            if ($code === '') {
                continue;
            }

            $employee = $employees->get($code);
            if (! $employee) {
                $skipped++;
                $errors[] = "Sheet công dòng {$rowNum}: không tìm thấy NV « {$code} ».";
                continue;
            }

            $summary = AttendanceSummary::firstOrNew([
                'employee_id' => $employee->id,
                'period' => $period,
            ]);

            if ($summary->exists && $summary->is_locked) {
                $skipped++;
                $errors[] = "Sheet công dòng {$rowNum}: công tháng {$period} của {$code} đã khóa.";
                continue;
            }

            $work = [];
            $nightHours = 0.0;
            foreach (config('cong_luong_import.cong_scalar_columns', []) as $col => $key) {
                $value = $this->numericValue($row[$col] ?? 0);
                if ($key === 'night_hours_summary') {
                    $nightHours = $value;
                    continue;
                }
                if ($key === 'standard_work_days') {
                    continue;
                }
                $work[$key] = $value;
            }

            $standardDays = $this->numericValue($row['AM'] ?? 26);
            $breakdown = $this->buildBreakdownFromRow($row, $period, $work, $standardDays);
            $workDays = (float) ($breakdown['work']['payable_work_days'] ?? 0);
            $leaveByType = $breakdown['leave_by_type'] ?? [];
            $paidLeave = round(
                (float) ($leaveByType['annual'] ?? 0)
                + (float) ($leaveByType['wedding'] ?? 0)
                + (float) ($leaveByType['maternity'] ?? 0)
                + (float) ($leaveByType['funeral'] ?? 0)
                + (float) ($leaveByType['company'] ?? 0),
                2,
            );
            $unpaidLeave = round(
                (float) ($leaveByType['personal'] ?? 0)
                + (float) ($leaveByType['sick'] ?? 0)
                + (float) ($leaveByType['unauthorized'] ?? 0),
                2,
            );
            $otGrid = $breakdown['ot'] ?? [];
            $otTotal = round(array_sum(array_map('floatval', $otGrid)), 2);

            $employmentStatus = (string) ($breakdown['meta']['employment_status'] ?? 'mixed');
            $joinDate = $this->parseJoinDate($row['AL'] ?? null);
            $phaseSplit = $this->resolveImportPhaseWorkDays($employee, $period, $workDays, $employmentStatus, $joinDate);
            $leavePhaseSplit = $this->workDaysSplitter->splitAllLeaveDaysByPhaseWeights(
                $employee,
                $period,
                $paidLeave,
                $unpaidLeave,
            );
            $periodStart = Carbon::createFromFormat('!Y-m-d', $period.'-01');
            $periodEnd = $periodStart->copy()->endOfMonth();
            $probationEnd = $this->phaseResolver->probationEndInPeriod($employee, $periodStart, $periodEnd);
            if ($probationEnd) {
                $breakdown['meta']['probation_end_date'] = $probationEnd->format('Y-m-d');
            }
            $breakdown['meta']['has_phase_split'] = $phaseSplit['probation_work_days'] > 0
                && $phaseSplit['official_work_days'] > 0;
            $breakdown['work']['probation_work_days'] = $phaseSplit['probation_work_days'];
            $breakdown['work']['official_work_days'] = $phaseSplit['official_work_days'];
            $breakdown['leave_by_phase'] = [
                'probation' => [
                    'paid' => $leavePhaseSplit['probation_paid_leave_days'],
                    'unpaid' => $leavePhaseSplit['probation_unpaid_leave_days'],
                    'by_type' => [],
                ],
                'official' => [
                    'paid' => $leavePhaseSplit['official_paid_leave_days'],
                    'unpaid' => $leavePhaseSplit['official_unpaid_leave_days'],
                    'by_type' => [],
                ],
            ];
            if ($joinDate) {
                $breakdown['meta']['join_date_in_period'] = $joinDate->format('Y-m-d');
                $breakdown['work']['join_date'] = $joinDate->format('Y-m-d');
            }

            $summary->fill([
                'company_id' => $companyId,
                'work_days' => $workDays,
                'probation_work_days' => $phaseSplit['probation_work_days'],
                'official_work_days' => $phaseSplit['official_work_days'],
                'standard_work_days' => $standardDays,
                'paid_leave_days' => $paidLeave,
                'unpaid_leave_days' => $unpaidLeave,
                'probation_paid_leave_days' => $leavePhaseSplit['probation_paid_leave_days'],
                'official_paid_leave_days' => $leavePhaseSplit['official_paid_leave_days'],
                'probation_unpaid_leave_days' => $leavePhaseSplit['probation_unpaid_leave_days'],
                'official_unpaid_leave_days' => $leavePhaseSplit['official_unpaid_leave_days'],
                'leave_days' => round($paidLeave + $unpaidLeave, 2),
                'night_hours' => $nightHours,
                'ot_hours' => $otTotal,
                'attendance_breakdown' => $breakdown,
                'is_locked' => false,
            ]);
            $summary->save();

            $imported++;
        }

        return compact('imported', 'skipped', 'errors');
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Employee>  $employees
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    private function importLuongSheet(
        int $companyId,
        string $period,
        string $filepath,
        string $sheetPath,
        $employees,
    ): array {
        $cfg = config('cong_luong_import.sheet_luong');
        $rows = $this->reader->readSheetKeyedRows($filepath, $sheetPath, (int) ($cfg['data_start_row'] ?? 3));
        $codeCol = (string) ($cfg['employee_code_column'] ?? 'B');

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $row) {
            $rowNum = (int) ($row['_row'] ?? 0);
            $code = strtoupper(trim((string) ($row[$codeCol] ?? '')));

            if ($code === '') {
                continue;
            }

            $employee = $employees->get($code);
            if (! $employee) {
                $skipped++;
                $errors[] = "Sheet lương dòng {$rowNum}: không tìm thấy NV « {$code} ».";
                continue;
            }

            $allowances = [];
            foreach (config('cong_luong_import.luong_allowance_columns', []) as $col => $catalogCode) {
                $allowances[$catalogCode] = $this->numericValue($row[$col] ?? 0);
            }

            $travelAmount = $this->numericValue($row[config('cong_luong_import.luong_travel_column', 'R')] ?? 0);
            $travelFlag = trim((string) ($row[config('cong_luong_import.luong_travel_flag_column', 'S')] ?? ''));
            $travelEligible = $travelFlag !== '' && $travelFlag !== '-';

            $notes = trim((string) ($row[config('cong_luong_import.luong_notes_column', 'AA')] ?? ''));

            $this->allowanceService->upsert($companyId, [
                'employee_id' => $employee->id,
                'period' => $period,
                'allowances' => $allowances,
                'travel_support_amount' => $travelAmount,
                'travel_eligible' => $travelEligible,
                'notes' => $notes !== '' ? $notes : null,
            ]);

            $imported++;
        }

        return compact('imported', 'skipped', 'errors');
    }

    /** @param  array<string, mixed>  $row */
    private function buildBreakdownFromRow(array $row, string $period, array $work, float $standardDays): array
    {
        $ot = [];
        foreach (config('cong_luong_import.cong_ot_columns', []) as $col => $key) {
            $ot[$key] = $this->numericValue($row[$col] ?? 0);
        }

        $leaveByType = [];
        foreach (config('cong_luong_import.cong_leave_columns', []) as $col => $key) {
            $leaveByType[$key] = $this->numericValue($row[$col] ?? 0);
        }

        $travelFlag = trim((string) ($row['AG'] ?? ''));
        $work['travel_support'] = ($travelFlag !== '' && $travelFlag !== '-') ? true : null;

        $statusRaw = trim((string) ($row['AN'] ?? ''));
        $employmentStatus = match (true) {
            str_contains($statusRaw, '试用'), str_contains(mb_strtolower($statusRaw), 'thử') => 'probation',
            str_contains($statusRaw, '正式'), str_contains(mb_strtolower($statusRaw), 'chính') => 'official',
            default => 'mixed',
        };

        $activeRaw = trim((string) ($row['AO'] ?? ''));
        $employmentActive = str_contains($activeRaw, '离职') || str_contains(mb_strtolower($activeRaw), 'nghỉ việc')
            ? config('cong_luong_sheet.employment_active_labels.inactive')
            : config('cong_luong_sheet.employment_active_labels.active');

        $joinDate = $this->parseJoinDate($row['AL'] ?? null);
        $work['resignation_days'] = $this->numericValue($row['AJ'] ?? 0);

        return [
            'version' => 1,
            'period' => $period,
            'source' => 'excel_import',
            'ot' => $ot,
            'leave_by_type' => $leaveByType,
            'work' => $work,
            'meta' => [
                'employment_status' => $employmentStatus,
                'employment_status_raw' => $statusRaw !== '' ? $statusRaw : $this->importStatusCode($employmentStatus),
                'employment_status_label' => match ($employmentStatus) {
                    'probation' => 'Thử việc',
                    'official' => 'Chính thức',
                    default => 'Hỗn hợp',
                },
                'employment_active_label' => $employmentActive,
                'resignation_note' => trim((string) ($row['AI'] ?? '')),
                'full_name_cn' => trim((string) ($row['C'] ?? '')),
                'department_cn' => trim((string) ($row['E'] ?? '')),
                'department_name' => trim((string) ($row['F'] ?? '')),
                'job_level' => $this->numericValue($row['G'] ?? 0),
                'join_date_in_period' => $joinDate?->format('Y-m-d'),
                'standard_work_days' => $standardDays,
                'import_employee_code' => trim((string) ($row['B'] ?? '')),
            ],
        ];
    }

    /**
     * @return array{probation_work_days: float, official_work_days: float}
     */
    private function resolveImportPhaseWorkDays(
        Employee $employee,
        string $period,
        float $workDays,
        string $employmentStatus,
        ?Carbon $joinDateInPeriod,
    ): array {
        $workDays = max(0, round($workDays, 2));
        if ($workDays <= 0) {
            return ['probation_work_days' => 0.0, 'official_work_days' => 0.0];
        }

        $periodStart = Carbon::createFromFormat('!Y-m-d', $period.'-01');
        $periodEnd = $periodStart->copy()->endOfMonth();

        if ($employmentStatus === 'probation') {
            return ['probation_work_days' => $workDays, 'official_work_days' => 0.0];
        }

        if ($employmentStatus === 'official') {
            $hasPhaseSplit = count($this->phaseResolver->phasesInPeriod($employee, $period)) > 1;
            $midMonthJoin = $joinDateInPeriod && $joinDateInPeriod->betweenIncluded($periodStart, $periodEnd);

            if ($midMonthJoin || ! $hasPhaseSplit) {
                return ['probation_work_days' => 0.0, 'official_work_days' => $workDays];
            }

            return $this->workDaysSplitter->splitWorkDays($employee, $period, $workDays);
        }

        return $this->workDaysSplitter->splitWorkDays($employee, $period, $workDays);
    }

    private function parseJoinDate(mixed $raw): ?Carbon
    {
        $value = trim((string) ($raw ?? ''));
        if ($value === '' || $value === '-') {
            return null;
        }

        $value = str_replace('.', '/', $value);

        foreach (['Y/m/d', 'Y-m-d', 'd/m/Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);
                if ($parsed instanceof Carbon) {
                    return $parsed->startOfDay();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function statusLabelFromCode(string $code): string
    {
        return (string) config("cong_luong_sheet.employment_status_labels.{$code}", 'Chính thức');
    }

    private function importStatusCode(string $code): string
    {
        return (string) config("cong_luong_sheet.import_status_codes.{$code}", '正式');
    }

    private function numericValue(mixed $value): float
    {
        if ($value === null || $value === '' || $value === '-') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $clean = preg_replace('/[^\d.,\-]/', '', (string) $value);
        $clean = str_replace(',', '.', $clean ?? '');

        return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
    }
}
