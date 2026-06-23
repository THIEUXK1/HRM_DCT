<?php

namespace Database\Seeders;

use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\AttendanceSummary;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeePayrollAllowance;
use App\Models\EmploymentContract;
use App\Services\Attendance\AttendanceSummaryService;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\PayrollCycle;
use App\Models\PayrollResult;
use App\Models\Position;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Tạo 10 nhân viên mẫu đầy đủ dữ liệu để test luồng:
 * hồ sơ NV → hợp đồng → người phụ thuộc → chấm công cả tháng → OT → tính công/lương.
 *
 * Idempotent: dùng firstOrCreate theo employee_code (EMP-101..EMP-110),
 * có thể chạy lại nhiều lần mà không nhân đôi dữ liệu.
 */
class SampleEmployeesSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'COMP-001')->first() ?? Company::first();
        if (! $company) {
            $this->command?->warn('SampleEmployeesSeeder: chưa có công ty nào, bỏ qua.');
            return;
        }

        $this->cleanTestData($company);

        (new HcmPlatformSeeder())->syncLeaveTypesForCompany($company->id);

        $branch = Branch::where('company_id', $company->id)->first()
            ?? Branch::firstOrCreate(
                ['company_id' => $company->id, 'code' => 'BR-001'],
                ['name' => 'Trụ sở chính', 'is_active' => true]
            );

        $shift = WorkShift::where('company_id', $company->id)->where('is_active', true)->orderBy('id')->first()
            ?? WorkShift::firstOrCreate(
                ['company_id' => $company->id, 'code' => 'CA-HC'],
                ['name' => 'Ca hành chính', 'start_time' => '08:30:00', 'end_time' => '17:30:00', 'break_minutes' => 60, 'is_active' => true]
            );

        $device = AttendanceDevice::where('company_id', $company->id)->first()
            ?? AttendanceDevice::firstOrCreate(
                ['code' => 'DEVICE-001'],
                ['company_id' => $company->id, 'name' => 'Máy chấm công cổng chính', 'vendor' => 'Generic', 'import_format' => 'csv_generic']
            );

        $departments = $this->ensureDepartments($branch->id);
        $positions = $this->ensurePositions($departments);

        // NV TV bắt đầu từ 01/04 → 2 tháng TV kết thúc 31/05, chính thức từ 01/06.
        // NV chính thức vào làm từ trước theo số năm kinh nghiệm.
        foreach ($this->employeeData() as $data) {
            $data['hire_date'] = $data['probation_months'] > 0
                ? '2026-04-01'
                : now()->subYears(min((int) $data['experience_years'], 6))->startOfYear()->toDateString();

            $employee = $this->createEmployee($company->id, $branch->id, $departments, $positions, $data);
            $this->createContract($employee, $data);
            $this->createDependents($employee, $data);
            // Tạo chấm công cho cả 2 tháng test; OT + nghỉ phép chỉ đặt vào tháng 5.
            $this->createAttendance($company->id, $employee, $device->id, $shift, $data, '2026-04');
            $this->createAttendance($company->id, $employee, $device->id, $shift, $data, '2026-05');
            $this->createOvertime($company->id, $employee, $data, '2026-05');
            $this->createLeave($company->id, $employee, $data, '2026-05');
        }

        $this->seedPhaseSplitDemos($company->id, $branch->id, $departments, $positions, $device->id, $shift);

        $this->syncSampleAttendanceSummaries($company->id, ['2026-04', '2026-05']);
        foreach (['2026-04', '2026-05'] as $resetPeriod) {
            $this->resetOpenPayrollCycles($company->id, $resetPeriod);
        }

        $this->command?->info('SampleEmployeesSeeder: đã tạo/cập nhật 10 nhân viên mẫu + chấm công tháng 04 và 05/2026.');
        $this->command?->info('  → EMP-TVCT + EMP-LVS (Lê Văn Sơn): TV→CT trong tháng 05/2026 (phase split test).');
        $this->command?->info('  → Bảng công 04+05 đã rebuild; kỳ lương chưa khóa đã xóa — vào Lương → Tạo/tính lại kỳ để test.');
    }

    private function seedPhaseSplitDemos(
        int $companyId,
        int $branchId,
        array $departments,
        array $positions,
        int $deviceId,
        WorkShift $shift,
    ): void {
        // EMP-TVCT: vào làm 01/05, hết TV 15/05, chính thức từ 16/05 → phase split trong tháng 5
        $this->createPhaseSplitEmployee($companyId, $branchId, $departments, $positions, $deviceId, [
            'code' => 'EMP-TVCT',
            'contract_prefix' => 'TVCT',
            'period' => '2026-05',
            'first_name' => 'Lan',
            'last_name' => 'Phase Split',
            'full_name' => 'Nguyễn Thị Lan (TV→CT)',
            'gender' => 'female',
            'dob' => '1998-04-12',
            'email' => 'lan.phasesplit@hrmglobal.local',
            'phone' => '0901000999',
            'dep' => 'HR',
            'pos' => 'HR-SPEC',
            'title' => 'Chuyên viên Nhân sự',
            'national_id' => '001098000999',
            'tax_code' => '8100000999',
            'social_insurance_number' => '0100000999',
            'salary_base' => 16_000_000,
            'probation_salary' => 16_000_000,
            'insurance_salary' => 15_000_000,
            'contract_type' => 'fixed_term',
            'probation_months' => 2,
            'hire_date' => '2026-05-01',
            'probation_end_date' => '2026-05-15',
            'note' => 'NV mẫu test TV→CT — hết TV ngày 15/05, chính thức từ 16/05/2026.',
            'ot' => [
                ['day' => 10, 'hours' => 2, 'type' => 'weekday'],  // TV phase (trước 15/05)
                ['day' => 20, 'hours' => 3, 'type' => 'weekday'],  // CT phase (sau 16/05)
            ],
            'allowances' => [
                'allowance_position' => 1_000_000,
                'allowance_meal' => 780_000,
                'allowance_housing_distance' => 500_000,
                'allowance_health_check' => 150_000,
            ],
        ]);

        $this->createPhaseSplitEmployee($companyId, $branchId, $departments, $positions, $deviceId, [
            'code' => 'EMP-LVS',
            'contract_prefix' => 'LVS',
            'period' => '2026-05',
            'first_name' => 'Sơn',
            'last_name' => 'Lê Văn',
            'full_name' => 'Lê Văn Sơn',
            'gender' => 'male',
            'dob' => '1995-08-03',
            'email' => 'son.levan@hrmglobal.local',
            'phone' => '0901000888',
            'dep' => 'OPS',
            'pos' => 'OPS-STAFF',
            'title' => 'Nhân viên Vận hành',
            'national_id' => '001095008888',
            'tax_code' => '8100000888',
            'social_insurance_number' => '0100000888',
            'salary_base' => 15_000_000,
            'probation_salary' => 15_000_000,  // 100% theo chính sách công ty
            'insurance_salary' => 14_000_000,
            'contract_type' => 'fixed_term',
            'probation_months' => 2,
            'hire_date' => '2026-04-01',
            'probation_end_date' => '2026-05-20',
            'note' => 'NV test TV→CT — hết thử việc 20/05/2026, chính thức từ 21/05/2026.',
            'ot' => [
                ['day' => 18, 'hours' => 2, 'type' => 'weekday'],
                ['day' => 27, 'hours' => 4, 'type' => 'weekday'],
            ],
            'allowances' => [
                'allowance_position' => 800_000,
                'allowance_meal' => 780_000,
                'allowance_housing' => 500_000,
                'incentive_bonus' => 150_000,
            ],
            'extra_periods' => ['2026-04'],
        ]);

        // Tổng hợp công gọi ở cuối run() qua syncSampleAttendanceSummaries().
    }

    /** Xóa toàn bộ dữ liệu nhân viên test EMP-* để seed lại sạch. */
    private function cleanTestData(Company $company): void
    {
        $ids = Employee::withTrashed()
            ->where('company_id', $company->id)
            ->where('employee_code', 'like', 'EMP-%')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        AttendanceLog::whereIn('employee_id', $ids)->delete();
        AttendanceSummary::whereIn('employee_id', $ids)->delete();
        OvertimeRequest::whereIn('employee_id', $ids)->delete();
        LeaveRequest::whereIn('employee_id', $ids)->delete();
        EmployeePayrollAllowance::whereIn('employee_id', $ids)->delete();
        EmploymentContract::withTrashed()->whereIn('employee_id', $ids)->forceDelete();

        $cycleIds = PayrollCycle::where('company_id', $company->id)->pluck('id');
        if ($cycleIds->isNotEmpty()) {
            PayrollResult::whereIn('employee_id', $ids)
                ->whereIn('payroll_cycle_id', $cycleIds)
                ->delete();
        }

        Employee::withTrashed()->whereIn('id', $ids)->each(function ($emp) {
            $emp->profile()->delete();
            $emp->dependents()->delete();
        });

        Employee::withTrashed()->whereIn('id', $ids)->forceDelete();

        $this->command?->info('  → Đã xóa '.count($ids).' nhân viên test cũ (EMP-*).');
    }

    /**
     * Rebuild bảng công tháng cho toàn bộ NV mẫu (EMP-101..110, EMP-TVCT, EMP-LVS…).
     *
     * @param  list<string>  $periods
     */
    private function syncSampleAttendanceSummaries(int $companyId, array $periods): void
    {
        $service = app(AttendanceSummaryService::class);
        $employees = Employee::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('employee_code', 'like', 'EMP-%')
            ->orderBy('employee_code')
            ->get(['id', 'employee_code']);

        foreach ($employees as $employee) {
            foreach ($periods as $period) {
                if (! $this->employeeHasLogsInPeriod($employee->id, $period)) {
                    continue;
                }
                try {
                    $service->rebuildEmployeePeriod($employee->id, $companyId, $period);
                } catch (\Throwable $e) {
                    $this->command?->warn("{$employee->employee_code} rebuild {$period}: ".$e->getMessage());
                }
            }
        }
    }

    /** Xóa kỳ lương chưa khóa để có thể tính lại với payslip_attendance mới. */
    private function resetOpenPayrollCycles(int $companyId, string $period): void
    {
        $deleted = PayrollCycle::where('company_id', $companyId)
            ->where('period', $period)
            ->where('status', '!=', 'locked')
            ->delete();

        if ($deleted > 0) {
            $this->command?->info("  → Đã xóa {$deleted} kỳ lương chưa khóa ({$period}).");
        }
    }

    private function employeeHasLogsInPeriod(int $employeeId, string $period): bool
    {
        $start = Carbon::createFromFormat('!Y-m-d', $period.'-01');
        $end = $start->copy()->endOfMonth();

        return AttendanceLog::where('employee_id', $employeeId)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->exists();
    }

    /**
     * NV demo: hết thử việc giữa tháng — kiểm tra tách TV/CT trên bảng công & lương.
     *
     * @param  array<string, mixed>  $data
     */
    private function createPhaseSplitEmployee(
        int $companyId,
        int $branchId,
        array $departments,
        array $positions,
        int $deviceId,
        array $data,
    ): void {
        $period = (string) $data['period'];
        $probationEnd = Carbon::parse($data['probation_end_date']);
        $officialStart = $probationEnd->copy()->addDay();

        $employee = Employee::updateOrCreate(
            ['employee_code' => $data['code']],
            [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'department_id' => $departments[$data['dep']]->id,
                'position_id' => $positions[$data['pos']]->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'gender' => $data['gender'],
                'date_of_birth' => $data['dob'],
                'hire_date' => $data['hire_date'],
                'probation_end_date' => $data['probation_end_date'],
                'official_start_date' => $officialStart->toDateString(),
                'employment_status' => now()->gte($officialStart) ? 'active' : 'probation',
                'employment_type' => 'full_time',
                'insurance_salary' => $data['insurance_salary'],
                'note' => $data['note'],
                'is_active' => true,
            ]
        );

        $this->createPhaseSplitContracts($employee, $data);

        foreach ($data['extra_periods'] ?? [] as $extraPeriod) {
            $this->createPhaseSplitAttendance($companyId, $employee, $deviceId, (string) $extraPeriod);
        }

        $this->createPhaseSplitAttendance($companyId, $employee, $deviceId, $period);
        $this->createOvertimeForPeriod($companyId, $employee, $data['ot'] ?? [], $period);
        $this->seedPhaseSplitPayrollAllowance($companyId, $employee, $period, $data['allowances'] ?? []);

        $periodsToSummarize = array_values(array_unique(array_merge(
            [$period],
            array_map('strval', $data['extra_periods'] ?? []),
        )));
        foreach ($periodsToSummarize as $summaryPeriod) {
            $this->buildPhaseSplitAttendanceSummary($companyId, $employee, $summaryPeriod, $data['code']);
        }
    }

    /** @param  array<string, mixed>  $d */
    private function createPhaseSplitContracts(Employee $employee, array $d): void
    {
        $hire = Carbon::parse($d['hire_date']);
        $probationEnd = Carbon::parse($d['probation_end_date']);
        $officialStart = $probationEnd->copy()->addDay();
        // Chính sách công ty: TV hưởng 100% lương vị trí; fallback về salary_base nếu không khai báo
        $probationSalary = (int) ($d['probation_salary'] ?? $d['salary_base']);
        $prefix = (string) ($d['contract_prefix'] ?? 'TVCT');

        EmploymentContract::updateOrCreate(
            ['contract_number' => 'CTR-'.$prefix.'-PB'],
            [
                'employee_id' => $employee->id,
                'contract_type' => 'probation',
                'job_title_on_contract' => $d['title'].' (Thử việc)',
                'work_location' => 'Hà Nội - Trụ sở chính',
                'start_date' => $hire->toDateString(),
                'signed_date' => $hire->toDateString(),
                'end_date' => $probationEnd->toDateString(),
                'probation_months' => (int) $d['probation_months'],
                'probation_salary' => $probationSalary,
                'salary_base' => $probationSalary,
                'insurance_salary' => $d['insurance_salary'],
                'salary_currency' => 'VND',
                'working_hours' => 'full_time_48',
                'work_schedule' => 'Thứ 2 – Thứ 7, 08:30–17:30',
                'signed_by_employer' => 'Giám đốc HRM Global',
                'signed_by_employee' => $d['full_name'],
                'status' => 'expired',
                'notes' => 'HĐ thử việc — hết hiệu lực khi chuyển CT giữa tháng.',
            ]
        );

        EmploymentContract::updateOrCreate(
            ['contract_number' => 'CTR-'.$prefix.'-CT'],
            [
                'employee_id' => $employee->id,
                'contract_type' => 'official',
                'job_title_on_contract' => $d['title'],
                'work_location' => 'Hà Nội - Trụ sở chính',
                'start_date' => $officialStart->toDateString(),
                'signed_date' => $officialStart->toDateString(),
                'end_date' => $d['contract_type'] === 'fixed_term'
                    ? $hire->copy()->addYear()->toDateString()
                    : null,
                'probation_months' => 0,
                'probation_salary' => $probationSalary,
                'salary_base' => $d['salary_base'],
                'insurance_salary' => $d['insurance_salary'],
                'salary_currency' => 'VND',
                'working_hours' => 'full_time_48',
                'work_schedule' => 'Thứ 2 – Thứ 7, 08:30–17:30',
                'signed_by_employer' => 'Giám đốc HRM Global',
                'signed_by_employee' => $d['full_name'],
                'status' => 'active',
                'notes' => 'HĐ chính thức — có hiệu lực từ sau ngày hết thử việc.',
            ]
        );
    }

    /** @param  array<string, float|int>  $allowances */
    private function seedPhaseSplitPayrollAllowance(
        int $companyId,
        Employee $employee,
        string $period,
        array $allowances,
    ): void {
        EmployeePayrollAllowance::updateOrCreate(
            ['employee_id' => $employee->id, 'period' => $period],
            [
                'company_id' => $companyId,
                'allowances' => $allowances,
                'travel_support_amount' => 0,
                'travel_eligible' => false,
                'notes' => 'Mẫu test TV→CT — trợ cấp tháng '.$period,
            ],
        );
    }

    private function buildPhaseSplitAttendanceSummary(
        int $companyId,
        Employee $employee,
        string $period,
        string $employeeCode,
    ): void {
        try {
            app(AttendanceSummaryService::class)->rebuildEmployeePeriod($employee->id, $companyId, $period);
        } catch (\Throwable $e) {
            $this->command?->warn($employeeCode.': không tổng hợp được công tháng '.$period.' — '.$e->getMessage());
        }
    }

    private function createPhaseSplitAttendance(
        int $companyId,
        Employee $employee,
        int $deviceId,
        string $period,
    ): void {
        $start = Carbon::createFromFormat('!Y-m-d', $period.'-01');
        $end = $start->copy()->endOfMonth();
        $fixedHolidays = ['01-01', '04-30', '05-01', '09-02', '09-01'];
        $hireDate = $employee->hire_date ? Carbon::parse($employee->hire_date)->startOfDay() : null;

        AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->delete();

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($hireDate && $day->lt($hireDate)) {
                continue;
            }

            if ($day->isSunday() || in_array($day->format('m-d'), $fixedHolidays, true)) {
                continue;
            }

            AttendanceLog::create([
                'company_id' => $companyId,
                'employee_id' => $employee->id,
                'work_date' => $day->toDateString(),
                'attendance_device_id' => $deviceId,
                'check_in_at' => $day->format('Y-m-d').' 08:30:00',
                'check_out_at' => $day->format('Y-m-d').' 17:30:00',
                'source' => 'device',
            ]);
        }
    }

    private function ensureDepartments(int $branchId): array
    {
        $defs = [
            'HR'    => 'Hành chính - Nhân sự',
            'ENG'   => 'Kỹ thuật - Công nghệ',
            'SALES' => 'Kinh doanh',
            'FIN'   => 'Tài chính - Kế toán',
            'OPS'   => 'Vận hành',
        ];

        $departments = [];
        foreach ($defs as $key => $name) {
            $departments[$key] = Department::firstOrCreate(
                ['branch_id' => $branchId, 'code' => 'DEP-'.$key],
                ['name' => $name, 'is_active' => true]
            );
        }

        return $departments;
    }

    private function ensurePositions(array $departments): array
    {
        $defs = [
            'ENG-LEAD'   => ['dep' => 'ENG', 'name' => 'Trưởng nhóm Kỹ thuật', 'level' => 'Lead'],
            'ENG-DEV'    => ['dep' => 'ENG', 'name' => 'Lập trình viên', 'level' => 'Middle'],
            'ENG-QA'     => ['dep' => 'ENG', 'name' => 'Kiểm thử phần mềm', 'level' => 'Junior'],
            'SALES-MGR'  => ['dep' => 'SALES', 'name' => 'Trưởng phòng Kinh doanh', 'level' => 'Manager'],
            'SALES-EXEC' => ['dep' => 'SALES', 'name' => 'Nhân viên Kinh doanh', 'level' => 'Staff'],
            'FIN-ACC'    => ['dep' => 'FIN', 'name' => 'Kế toán viên', 'level' => 'Staff'],
            'FIN-LEAD'   => ['dep' => 'FIN', 'name' => 'Kế toán trưởng', 'level' => 'Manager'],
            'HR-SPEC'    => ['dep' => 'HR', 'name' => 'Chuyên viên Nhân sự', 'level' => 'Staff'],
            'OPS-STAFF'  => ['dep' => 'OPS', 'name' => 'Nhân viên Vận hành', 'level' => 'Staff'],
        ];

        $positions = [];
        foreach ($defs as $key => $def) {
            $positions[$key] = Position::firstOrCreate(
                ['department_id' => $departments[$def['dep']]->id, 'code' => 'POS-'.$key],
                ['name' => $def['name'], 'level' => $def['level'], 'is_active' => true]
            );
        }

        return $positions;
    }

    private function createEmployee(int $companyId, int $branchId, array $departments, array $positions, array $d): Employee
    {
        $employee = Employee::firstOrCreate(
            ['employee_code' => $d['code']],
            [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'department_id' => $departments[$d['dep']]->id,
                'position_id' => $positions[$d['pos']]->id,
                'first_name' => $d['first_name'],
                'last_name' => $d['last_name'],
                'full_name' => $d['full_name'],
                'email' => $d['email'],
                'phone' => $d['phone'],
                'gender' => $d['gender'],
                'date_of_birth' => $d['dob'],
                'place_of_birth' => $d['province'],
                'origin_place' => $d['province'],
                'hire_date' => $d['hire_date'],
                'probation_end_date' => Carbon::parse($d['hire_date'])->addMonths($d['probation_months'])->subDay()->toDateString(),
                'official_start_date' => Carbon::parse($d['hire_date'])->addMonths($d['probation_months'])->toDateString(),
                'employment_status' => 'active',
                'employment_type' => 'full_time',
                'work_location' => 'Hà Nội - Trụ sở chính',
                'work_email' => $d['email'],
                'work_phone' => $d['phone'],
                'national_id' => $d['national_id'],
                'id_card_type' => 'cccd',
                'id_card_issue_date' => '2021-03-10',
                'id_card_issue_place' => 'Cục Cảnh sát QLHC về TTXH',
                'tax_code' => $d['tax_code'],
                'social_insurance_number' => $d['social_insurance_number'],
                'health_insurance_card' => 'DN'.$d['social_insurance_number'],
                'bhxh_start_date' => $d['hire_date'],
                'insurance_salary' => $d['insurance_salary'],
                'pit_dependents_count' => count($d['dependents']),
                'bank_account' => $d['bank_account'],
                'bank_account_name' => mb_strtoupper($this->stripAccents($d['full_name'])),
                'bank_name' => $d['bank_name'],
                'bank_branch' => 'CN Hà Nội',
                'permanent_address' => $d['address'],
                'province' => $d['province'],
                'district' => $d['district'],
                'nationality' => 'VN',
                'address' => $d['address'],
                'city' => $d['province'],
                'country' => 'Vietnam',
                'note' => 'Dữ liệu mẫu test luồng hồ sơ → tính công.',
                'is_active' => true,
            ]
        );

        $employee->profile()->updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'marital_status' => $d['marital_status'],
                'education_level' => 'university',
                'education_institution' => $d['school'],
                'graduation_year' => Carbon::parse($d['dob'])->year + 22,
                'major' => $d['major'],
                'experience_years' => $d['experience_years'],
                'emergency_contact_name' => $d['emergency_name'],
                'emergency_contact_phone' => $d['emergency_phone'],
                'emergency_contact_relationship' => $d['emergency_rel'],
                'military_service_status' => $d['gender'] === 'male' ? 'completed' : 'exempt',
            ]
        );

        return $employee;
    }

    private function createContract(Employee $employee, array $d): void
    {
        EmploymentContract::updateOrCreate(
            ['contract_number' => 'CTR-'.substr($d['code'], 4)],
            [
                'employee_id' => $employee->id,
                'contract_type' => $d['contract_type'],
                'job_title_on_contract' => $d['title'],
                'work_location' => 'Hà Nội - Trụ sở chính',
                'start_date' => $d['hire_date'],
                'signed_date' => $d['hire_date'],
                'end_date' => $d['contract_type'] === 'fixed_term'
                    ? Carbon::parse($d['hire_date'])->addYear()->toDateString()
                    : null,
                'probation_months' => $d['probation_months'],
                // Chính sách công ty: TV hưởng 100% lương vị trí theo thỏa thuận
                'probation_salary' => (int) $d['salary_base'],
                'salary_base' => $d['salary_base'],
                'insurance_salary' => $d['insurance_salary'],
                'salary_currency' => 'VND',
                'working_hours' => 'full_time_48',
                'work_schedule' => 'Thứ 2 – Thứ 6, 08:30–17:30',
                'signed_by_employer' => 'Giám đốc HRM Global',
                'signed_by_employee' => $d['full_name'],
                'status' => 'active',
            ]
        );
    }

    private function createDependents(Employee $employee, array $d): void
    {
        foreach ($d['dependents'] as $i => $dep) {
            $employee->dependents()->firstOrCreate(
                ['full_name' => $dep['name'], 'relationship' => $dep['relationship']],
                [
                    'date_of_birth' => $dep['dob'],
                    'tax_dependent_code' => 'NPT-'.substr($d['code'], 4).'-'.($i + 1),
                    'effective_from' => $d['hire_date'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Tạo log chấm công cho toàn bộ ngày làm việc trong kỳ chỉ định
     * (loại trừ Chủ nhật và ngày lễ theo Điều 112 BLLĐ 2019).
     */
    private function createAttendance(int $companyId, Employee $employee, int $deviceId, WorkShift $shift, array $d, string $period = ''): void
    {
        $start = $period
            ? Carbon::createFromFormat('!Y-m-d', $period.'-01')
            : now()->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Ngày lễ cố định theo dương lịch (Điều 112 BLLĐ 2019) — bỏ qua khi chấm công.
        $fixedHolidays = ['01-01', '04-30', '05-01', '09-02', '09-01'];

        // Xóa log cũ trong kỳ để seeder idempotent (tránh đụng unique employee+work_date).
        AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->delete();

        // Ngày nghỉ phép chỉ áp dụng cho tháng tạo leave (2026-05), tránh bỏ sót công tháng khác.
        $leaveDays = [];
        if (! empty($d['leave']) && ($period === '2026-05' || $period === '')) {
            $from = (int) $d['leave']['from_day'];
            $to = (int) ($d['leave']['to_day'] ?? $from);
            for ($i = $from; $i <= $to; $i++) {
                $leaveDays[] = $i;
            }
        }

        // Tuần làm việc 6 ngày (T2–T7) khớp standard_work_days của hệ thống (chỉ trừ Chủ nhật + lễ).
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($day->isSunday() || in_array($day->format('m-d'), $fixedHolidays, true)) {
                continue;
            }

            // Nhân viên nghỉ một số ngày để tạo dữ liệu công đa dạng
            if (in_array($day->day, $d['absent_days'] ?? [], true)) {
                continue;
            }

            if (in_array($day->day, $leaveDays, true)) {
                continue;
            }

            // Một số ngày đi trễ để có dữ liệu trễ giờ
            $checkInTime = in_array($day->day, $d['late_days'] ?? [], true) ? '08:50:00' : '08:30:00';

            AttendanceLog::create([
                'company_id' => $companyId,
                'employee_id' => $employee->id,
                'work_date' => $day->toDateString(),
                'attendance_device_id' => $deviceId,
                'check_in_at' => $day->format('Y-m-d').' '.$checkInTime,
                'check_out_at' => $day->format('Y-m-d').' 17:30:00',
                'source' => 'device',
            ]);
        }
    }

    private function createLeave(int $companyId, Employee $employee, array $d, string $period = ''): void
    {
        if (empty($d['leave'])) {
            return;
        }

        $leaveType = LeaveType::where('company_id', $companyId)
            ->where('code', $d['leave']['type'] ?? 'PHEP')
            ->first();
        if (! $leaveType) {
            return;
        }

        $base = $period
            ? Carbon::createFromFormat('!Y-m-d', $period.'-01')
            : now()->startOfMonth();
        $start = $base->copy()->day($d['leave']['from_day'])->startOfDay();
        $end = $base->copy()->day($d['leave']['to_day'] ?? $d['leave']['from_day']);

        LeaveRequest::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            [
                'company_id' => $companyId,
                'leave_type_id' => $leaveType->id,
                'total_days' => $d['leave']['days'] ?? 1,
                'reason' => $d['leave']['reason'] ?? 'Nghỉ phép cá nhân',
                'status' => 'approved',
                'approved_at' => now(),
            ]
        );
    }

    /** @param  list<array{day: int, hours: float|int, type?: string}>  $otRows */
    private function createOvertimeForPeriod(int $companyId, Employee $employee, array $otRows, string $period): void
    {
        if ($otRows === []) {
            return;
        }

        $start = Carbon::createFromFormat('!Y-m-d', $period.'-01');
        $end = $start->copy()->endOfMonth();

        OvertimeRequest::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->delete();

        foreach ($otRows as $ot) {
            $date = $start->copy()->day((int) $ot['day']);

            OvertimeRequest::create([
                'company_id' => $companyId,
                'employee_id' => $employee->id,
                'work_date' => $date->toDateString(),
                'hours' => $ot['hours'],
                'ot_type' => $ot['type'] ?? 'weekday',
                'night_hours' => 0,
                'reason' => 'Hoàn thành công việc gấp cuối kỳ',
                'status' => 'approved',
                'approved_at' => now(),
            ]);
        }
    }

    private function createOvertime(int $companyId, Employee $employee, array $d, string $period = ''): void
    {
        if (empty($d['ot'])) {
            return;
        }

        $start = $period
            ? Carbon::createFromFormat('!Y-m-d', $period.'-01')
            : now()->startOfMonth();
        $end = $start->copy()->endOfMonth();

        OvertimeRequest::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->delete();

        foreach ($d['ot'] as $ot) {
            $date = $start->copy()->day((int) $ot['day']);
            if ($date->month !== $start->month) {
                continue;
            }

            OvertimeRequest::create([
                'company_id' => $companyId,
                'employee_id' => $employee->id,
                'work_date' => $date->toDateString(),
                'hours' => $ot['hours'],
                'ot_type' => $ot['type'] ?? 'weekday',
                'night_hours' => 0,
                'reason' => 'Hoàn thành công việc gấp cuối kỳ',
                'status' => 'approved',
                'approved_at' => now(),
            ]);
        }
    }

    private function stripAccents(string $str): string
    {
        $from = ['à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ','è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ','ì','í','ị','ỉ','ĩ','ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ','ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ','ỳ','ý','ỵ','ỷ','ỹ','đ'];
        $to   = ['a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','e','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y','d'];
        $str = str_replace($from, $to, mb_strtolower($str));
        return $str;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function employeeData(): array
    {
        return [
            [
                'code' => 'EMP-101', 'first_name' => 'Trần', 'last_name' => 'Quốc Bảo', 'full_name' => 'Trần Quốc Bảo',
                'gender' => 'male', 'dob' => '1988-02-12', 'email' => 'bao.tran@hrmglobal.local', 'phone' => '0901000101',
                'dep' => 'ENG', 'pos' => 'ENG-LEAD', 'title' => 'Trưởng nhóm Kỹ thuật',
                'national_id' => '001088000101', 'tax_code' => '8100000101', 'social_insurance_number' => '0100000101',
                'salary_base' => 35_000_000, 'insurance_salary' => 30_000_000, 'contract_type' => 'indefinite', 'probation_months' => 0,
                'bank_account' => '0011000101', 'bank_name' => 'Vietcombank',
                'province' => 'Hà Nội', 'district' => 'Cầu Giấy', 'address' => 'Số 5 Trần Duy Hưng, Cầu Giấy, Hà Nội',
                'marital_status' => 'married', 'school' => 'Đại học Bách Khoa Hà Nội', 'major' => 'Công nghệ thông tin', 'experience_years' => 10,
                'emergency_name' => 'Lê Thị Hoa', 'emergency_phone' => '0987000101', 'emergency_rel' => 'Vợ',
                'dependents' => [
                    ['name' => 'Trần Bảo An', 'relationship' => 'child', 'dob' => '2016-06-01'],
                    ['name' => 'Trần Bảo Châu', 'relationship' => 'child', 'dob' => '2019-09-15'],
                ],
                'late_days' => [6, 19], 'absent_days' => [], 'ot' => [['day' => 12, 'hours' => 8, 'type' => 'weekday']],
            ],
            [
                'code' => 'EMP-102', 'first_name' => 'Nguyễn', 'last_name' => 'Thị Mai', 'full_name' => 'Nguyễn Thị Mai',
                'gender' => 'female', 'dob' => '1993-07-25', 'email' => 'mai.nguyen@hrmglobal.local', 'phone' => '0901000102',
                'dep' => 'ENG', 'pos' => 'ENG-DEV', 'title' => 'Lập trình viên',
                'national_id' => '001093000102', 'tax_code' => '8100000102', 'social_insurance_number' => '0100000102',
                'salary_base' => 22_000_000, 'insurance_salary' => 20_000_000, 'contract_type' => 'indefinite', 'probation_months' => 0,
                'bank_account' => '0011000102', 'bank_name' => 'Techcombank',
                'province' => 'Hà Nội', 'district' => 'Đống Đa', 'address' => 'Số 18 Tây Sơn, Đống Đa, Hà Nội',
                'marital_status' => 'single', 'school' => 'Đại học Công nghệ - ĐHQGHN', 'major' => 'Kỹ thuật phần mềm', 'experience_years' => 5,
                'emergency_name' => 'Nguyễn Văn Hùng', 'emergency_phone' => '0987000102', 'emergency_rel' => 'Bố',
                'dependents' => [],
                'late_days' => [13], 'absent_days' => [], 'ot' => [['day' => 12, 'hours' => 6, 'type' => 'weekday']],
                'leave' => ['type' => 'PHEP', 'from_day' => 14, 'to_day' => 14, 'days' => 1, 'reason' => 'Việc gia đình'],
            ],
            [
                'code' => 'EMP-103', 'first_name' => 'Lê', 'last_name' => 'Hoàng Long', 'full_name' => 'Lê Hoàng Long',
                'gender' => 'male', 'dob' => '1995-11-03', 'email' => 'long.le@hrmglobal.local', 'phone' => '0901000103',
                'dep' => 'ENG', 'pos' => 'ENG-QA', 'title' => 'Kiểm thử phần mềm',
                'national_id' => '001095000103', 'tax_code' => '8100000103', 'social_insurance_number' => '0100000103',
                'salary_base' => 16_000_000, 'insurance_salary' => 15_000_000, 'contract_type' => 'fixed_term', 'probation_months' => 2,
                'bank_account' => '0011000103', 'bank_name' => 'BIDV',
                'province' => 'Bắc Ninh', 'district' => 'TP Bắc Ninh', 'address' => 'Số 22 Lý Thái Tổ, TP Bắc Ninh',
                'marital_status' => 'single', 'school' => 'Học viện Kỹ thuật Quân sự', 'major' => 'Khoa học máy tính', 'experience_years' => 2,
                'emergency_name' => 'Lê Văn Tâm', 'emergency_phone' => '0987000103', 'emergency_rel' => 'Bố',
                'dependents' => [],
                'late_days' => [7, 20, 27], 'absent_days' => [15], 'ot' => [],
            ],
            [
                'code' => 'EMP-104', 'first_name' => 'Phạm', 'last_name' => 'Văn Đức', 'full_name' => 'Phạm Văn Đức',
                'gender' => 'male', 'dob' => '1985-04-18', 'email' => 'duc.pham@hrmglobal.local', 'phone' => '0901000104',
                'dep' => 'SALES', 'pos' => 'SALES-MGR', 'title' => 'Trưởng phòng Kinh doanh',
                'national_id' => '001085000104', 'tax_code' => '8100000104', 'social_insurance_number' => '0100000104',
                'salary_base' => 30_000_000, 'insurance_salary' => 28_000_000, 'contract_type' => 'indefinite', 'probation_months' => 0,
                'bank_account' => '0011000104', 'bank_name' => 'Vietcombank',
                'province' => 'Hà Nội', 'district' => 'Hai Bà Trưng', 'address' => 'Số 90 Bạch Mai, Hai Bà Trưng, Hà Nội',
                'marital_status' => 'married', 'school' => 'Đại học Kinh tế Quốc dân', 'major' => 'Quản trị kinh doanh', 'experience_years' => 12,
                'emergency_name' => 'Trần Thị Lan', 'emergency_phone' => '0987000104', 'emergency_rel' => 'Vợ',
                'dependents' => [
                    ['name' => 'Phạm Minh Khoa', 'relationship' => 'child', 'dob' => '2014-02-20'],
                ],
                'late_days' => [], 'absent_days' => [], 'ot' => [['day' => 5, 'hours' => 4, 'type' => 'weekday']],
            ],
            [
                'code' => 'EMP-105', 'first_name' => 'Vũ', 'last_name' => 'Thị Hương', 'full_name' => 'Vũ Thị Hương',
                'gender' => 'female', 'dob' => '1996-08-30', 'email' => 'huong.vu@hrmglobal.local', 'phone' => '0901000105',
                'dep' => 'SALES', 'pos' => 'SALES-EXEC', 'title' => 'Nhân viên Kinh doanh',
                'national_id' => '001096000105', 'tax_code' => '8100000105', 'social_insurance_number' => '0100000105',
                'salary_base' => 14_000_000, 'insurance_salary' => 13_000_000, 'contract_type' => 'fixed_term', 'probation_months' => 2,
                'bank_account' => '0011000105', 'bank_name' => 'MB Bank',
                'province' => 'Hưng Yên', 'district' => 'Văn Lâm', 'address' => 'Thôn Đình, Văn Lâm, Hưng Yên',
                'marital_status' => 'single', 'school' => 'Đại học Thương mại', 'major' => 'Marketing', 'experience_years' => 1,
                'emergency_name' => 'Vũ Văn Nam', 'emergency_phone' => '0987000105', 'emergency_rel' => 'Anh trai',
                'dependents' => [],
                'late_days' => [9], 'absent_days' => [], 'ot' => [['day' => 12, 'hours' => 5, 'type' => 'weekday']],
                'leave' => ['type' => 'KL', 'from_day' => 22, 'to_day' => 22, 'days' => 1, 'reason' => 'Nghỉ không lương theo thỏa thuận'],
            ],
            [
                'code' => 'EMP-106', 'first_name' => 'Đặng', 'last_name' => 'Minh Tuấn', 'full_name' => 'Đặng Minh Tuấn',
                'gender' => 'male', 'dob' => '1991-12-09', 'email' => 'tuan.dang@hrmglobal.local', 'phone' => '0901000106',
                'dep' => 'FIN', 'pos' => 'FIN-LEAD', 'title' => 'Kế toán trưởng',
                'national_id' => '001091000106', 'tax_code' => '8100000106', 'social_insurance_number' => '0100000106',
                'salary_base' => 28_000_000, 'insurance_salary' => 26_000_000, 'contract_type' => 'indefinite', 'probation_months' => 0,
                'bank_account' => '0011000106', 'bank_name' => 'ACB',
                'province' => 'Hà Nội', 'district' => 'Hoàng Mai', 'address' => 'Số 12 Giải Phóng, Hoàng Mai, Hà Nội',
                'marital_status' => 'married', 'school' => 'Học viện Tài chính', 'major' => 'Kế toán - Kiểm toán', 'experience_years' => 9,
                'emergency_name' => 'Bùi Thị Thu', 'emergency_phone' => '0987000106', 'emergency_rel' => 'Vợ',
                'dependents' => [
                    ['name' => 'Đặng Gia Hân', 'relationship' => 'child', 'dob' => '2017-11-11'],
                ],
                'late_days' => [], 'absent_days' => [], 'ot' => [],
            ],
            [
                'code' => 'EMP-107', 'first_name' => 'Hoàng', 'last_name' => 'Thị Thu', 'full_name' => 'Hoàng Thị Thu',
                'gender' => 'female', 'dob' => '1994-03-22', 'email' => 'thu.hoang@hrmglobal.local', 'phone' => '0901000107',
                'dep' => 'FIN', 'pos' => 'FIN-ACC', 'title' => 'Kế toán viên',
                'national_id' => '001094000107', 'tax_code' => '8100000107', 'social_insurance_number' => '0100000107',
                'salary_base' => 15_000_000, 'insurance_salary' => 14_000_000, 'contract_type' => 'indefinite', 'probation_months' => 0,
                'bank_account' => '0011000107', 'bank_name' => 'Vietinbank',
                'province' => 'Hà Nội', 'district' => 'Long Biên', 'address' => 'Số 3 Ngọc Lâm, Long Biên, Hà Nội',
                'marital_status' => 'married', 'school' => 'Đại học Kinh tế Quốc dân', 'major' => 'Kế toán', 'experience_years' => 4,
                'emergency_name' => 'Phan Văn Lộc', 'emergency_phone' => '0987000107', 'emergency_rel' => 'Chồng',
                'dependents' => [
                    ['name' => 'Phan Bảo Ngọc', 'relationship' => 'child', 'dob' => '2021-05-05'],
                ],
                'late_days' => [21], 'absent_days' => [], 'ot' => [],
                'leave' => ['type' => 'OM', 'from_day' => 20, 'to_day' => 21, 'days' => 2, 'reason' => 'Nghỉ ốm có giấy BS'],
            ],
            [
                'code' => 'EMP-108', 'first_name' => 'Bùi', 'last_name' => 'Văn Sơn', 'full_name' => 'Bùi Văn Sơn',
                'gender' => 'male', 'dob' => '1990-10-14', 'email' => 'son.bui@hrmglobal.local', 'phone' => '0901000108',
                'dep' => 'HR', 'pos' => 'HR-SPEC', 'title' => 'Chuyên viên Nhân sự',
                'national_id' => '001090000108', 'tax_code' => '8100000108', 'social_insurance_number' => '0100000108',
                'salary_base' => 18_000_000, 'insurance_salary' => 17_000_000, 'contract_type' => 'indefinite', 'probation_months' => 0,
                'bank_account' => '0011000108', 'bank_name' => 'Vietcombank',
                'province' => 'Hà Nội', 'district' => 'Thanh Xuân', 'address' => 'Số 45 Khương Trung, Thanh Xuân, Hà Nội',
                'marital_status' => 'married', 'school' => 'Đại học Khoa học Xã hội & Nhân văn', 'major' => 'Quản trị nhân lực', 'experience_years' => 6,
                'emergency_name' => 'Đỗ Thị Hà', 'emergency_phone' => '0987000108', 'emergency_rel' => 'Vợ',
                'dependents' => [],
                'late_days' => [14], 'absent_days' => [], 'ot' => [],
            ],
            [
                'code' => 'EMP-109', 'first_name' => 'Đỗ', 'last_name' => 'Thị Lan', 'full_name' => 'Đỗ Thị Lan',
                'gender' => 'female', 'dob' => '1997-01-19', 'email' => 'lan.do@hrmglobal.local', 'phone' => '0901000109',
                'dep' => 'OPS', 'pos' => 'OPS-STAFF', 'title' => 'Nhân viên Vận hành',
                'national_id' => '001097000109', 'tax_code' => '8100000109', 'social_insurance_number' => '0100000109',
                'salary_base' => 12_000_000, 'insurance_salary' => 11_500_000, 'contract_type' => 'fixed_term', 'probation_months' => 2,
                'bank_account' => '0011000109', 'bank_name' => 'TPBank',
                'province' => 'Hà Nam', 'district' => 'Phủ Lý', 'address' => 'Số 7 Lê Lợi, Phủ Lý, Hà Nam',
                'marital_status' => 'single', 'school' => 'Cao đẳng Công nghiệp Hà Nội', 'major' => 'Quản trị logistics', 'experience_years' => 2,
                'emergency_name' => 'Đỗ Văn Khánh', 'emergency_phone' => '0987000109', 'emergency_rel' => 'Bố',
                'dependents' => [],
                'late_days' => [8, 22], 'absent_days' => [16], 'ot' => [['day' => 12, 'hours' => 4, 'type' => 'weekday']],
            ],
            [
                'code' => 'EMP-110', 'first_name' => 'Ngô', 'last_name' => 'Văn Hải', 'full_name' => 'Ngô Văn Hải',
                'gender' => 'male', 'dob' => '1989-06-07', 'email' => 'hai.ngo@hrmglobal.local', 'phone' => '0901000110',
                'dep' => 'OPS', 'pos' => 'OPS-STAFF', 'title' => 'Nhân viên Vận hành',
                'national_id' => '001089000110', 'tax_code' => '8100000110', 'social_insurance_number' => '0100000110',
                'salary_base' => 13_500_000, 'insurance_salary' => 13_000_000, 'contract_type' => 'indefinite', 'probation_months' => 0,
                'bank_account' => '0011000110', 'bank_name' => 'Agribank',
                'province' => 'Nam Định', 'district' => 'TP Nam Định', 'address' => 'Số 30 Trần Hưng Đạo, TP Nam Định',
                'marital_status' => 'married', 'school' => 'Cao đẳng Kinh tế Kỹ thuật', 'major' => 'Quản lý kho vận', 'experience_years' => 7,
                'emergency_name' => 'Trịnh Thị Nga', 'emergency_phone' => '0987000110', 'emergency_rel' => 'Vợ',
                'dependents' => [
                    ['name' => 'Ngô Khánh Vy', 'relationship' => 'child', 'dob' => '2015-08-08'],
                    ['name' => 'Ngô Gia Bảo', 'relationship' => 'child', 'dob' => '2020-12-01'],
                ],
                'late_days' => [], 'absent_days' => [], 'ot' => [['day' => 12, 'hours' => 3, 'type' => 'weekday']],
            ],
        ];
    }
}
