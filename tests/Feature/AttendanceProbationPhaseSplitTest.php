<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\AttendanceSummary;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\Tenant;
use App\Models\WorkShift;
use App\Services\Attendance\AttendanceSummaryService;
use App\Services\Payroll\PayrollEarningsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceProbationPhaseSplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_summary_splits_work_days_when_probation_ends_mid_month(): void
    {
        $tenant = Tenant::create(['code' => 'T1', 'name' => 'T1']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C1', 'name' => 'C1']);

        WorkShift::create([
            'company_id' => $company->id,
            'code' => 'CA1',
            'name' => 'Ca 1',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_minutes' => 60,
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'NV-TV01',
            'first_name' => 'An',
            'last_name' => 'Nguyen',
            'full_name' => 'Nguyen Van An',
            'email' => 'an@test.local',
            'hire_date' => '2026-05-01',
            'probation_end_date' => '2026-05-15',
            'insurance_salary' => 20_000_000,
            'is_active' => true,
        ]);

        EmploymentContract::create([
            'employee_id' => $employee->id,
            'contract_number' => 'HD-001',
            'contract_type' => 'fixed_term',
            'status' => 'active',
            'start_date' => '2026-05-01',
            'salary_base' => 20_000_000,
            'probation_salary' => 17_000_000,
            'probation_months' => 2,
            'insurance_salary' => 20_000_000,
        ]);

        $probationDates = [
            '2026-05-01', '2026-05-02', '2026-05-03', '2026-05-04', '2026-05-05',
            '2026-05-06', '2026-05-07', '2026-05-08', '2026-05-09', '2026-05-10',
            '2026-05-11', '2026-05-12', '2026-05-13', '2026-05-14', '2026-05-15',
        ];
        $officialDates = [
            '2026-05-18', '2026-05-19', '2026-05-20', '2026-05-21', '2026-05-22',
            '2026-05-25', '2026-05-26', '2026-05-27', '2026-05-28',
        ];
        foreach ($probationDates as $date) {
            AttendanceLog::create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'work_date' => $date,
                'check_in_at' => "{$date} 08:00:00",
                'check_out_at' => "{$date} 17:00:00",
                'source' => 'manual',
            ]);
        }
        foreach ($officialDates as $date) {
            AttendanceLog::create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'work_date' => $date,
                'check_in_at' => "{$date} 08:00:00",
                'check_out_at' => "{$date} 17:00:00",
                'source' => 'manual',
            ]);
        }

        $period = '2026-05';
        app(AttendanceSummaryService::class)->buildForPeriod($company->id, $period);

        $summary = AttendanceSummary::where('employee_id', $employee->id)
            ->where('period', $period)
            ->first();

        $this->assertNotNull($summary);
        $expectedTotal = count($probationDates) + count($officialDates);
        $this->assertEquals((float) $expectedTotal, (float) $summary->work_days);
        $this->assertEquals((float) count($probationDates), (float) $summary->probation_work_days);
        $this->assertEquals((float) count($officialDates), (float) $summary->official_work_days);
        $this->assertEquals('mixed', $summary->attendance_breakdown['meta']['employment_status']);
        $this->assertTrue($summary->attendance_breakdown['meta']['has_phase_split']);

        $contract = EmploymentContract::where('employee_id', $employee->id)->first();
        $earned = app(PayrollEarningsService::class)->calculateGross($employee, $contract, $summary, $period);

        $this->assertTrue($earned['breakdown']['has_phase_split']);
        $this->assertEquals((float) count($probationDates), $earned['breakdown']['probation_work_days']);
        $this->assertEquals((float) count($officialDates), $earned['breakdown']['official_work_days']);
        $this->assertGreaterThan(0, $earned['breakdown']['probation_base_pay']);
        $this->assertGreaterThan(0, $earned['breakdown']['official_base_pay']);
    }

    public function test_payroll_splits_when_summary_missing_phase_columns(): void
    {
        $tenant = Tenant::create(['code' => 'T2', 'name' => 'T2']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C2', 'name' => 'C2']);

        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'NV-TV02',
            'first_name' => 'Binh',
            'last_name' => 'Tran',
            'full_name' => 'Tran Van Binh',
            'email' => 'binh@test.local',
            'hire_date' => '2026-05-01',
            'probation_end_date' => '2026-05-15',
            'is_active' => true,
        ]);

        $contract = EmploymentContract::create([
            'employee_id' => $employee->id,
            'contract_number' => 'HD-002',
            'contract_type' => 'fixed_term',
            'status' => 'active',
            'start_date' => '2026-05-01',
            'salary_base' => 20_000_000,
            'probation_salary' => 17_000_000,
            'probation_months' => 2,
        ]);

        $summary = new AttendanceSummary([
            'standard_work_days' => 25,
            'work_days' => 24,
            'probation_work_days' => 0,
            'official_work_days' => 0,
            'leave_days' => 0,
            'absent_days' => 1,
        ]);

        $earned = app(PayrollEarningsService::class)->calculateGross($employee, $contract, $summary, '2026-05');

        $this->assertTrue($earned['breakdown']['has_phase_split']);
        $this->assertGreaterThan(0, $earned['breakdown']['probation_work_days']);
        $this->assertGreaterThan(0, $earned['breakdown']['official_work_days']);
        $this->assertEquals(24.0, $earned['breakdown']['probation_work_days'] + $earned['breakdown']['official_work_days']);
        $this->assertGreaterThan(0, $earned['breakdown']['probation_base_pay']);
        $this->assertGreaterThan(0, $earned['breakdown']['official_base_pay']);
    }
}
