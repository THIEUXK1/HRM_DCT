<?php

namespace Tests\Feature;

use App\Models\AttendanceSummary;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeePayrollAllowance;
use App\Models\EmploymentContract;
use App\Models\Tenant;
use Database\Seeders\HcmExtendedSeeder;
use Database\Seeders\HcmPlatformSeeder;
use Database\Seeders\InitialHrDataSeeder;
use Database\Seeders\SampleEmployeesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SampleEmployeeTvCtSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_emp_tvct_has_probation_and_official_work_in_same_month(): void
    {
        $this->seed(InitialHrDataSeeder::class);
        $this->seed(HcmPlatformSeeder::class);
        $this->seed(HcmExtendedSeeder::class);
        $this->seed(SampleEmployeesSeeder::class);

        $employee = Employee::where('employee_code', 'EMP-TVCT')->first();
        $this->assertNotNull($employee);
        $this->assertNotNull($employee->probation_end_date);
        $this->assertNotNull($employee->official_start_date);

        $period = '2026-05';
        $summary = AttendanceSummary::where('employee_id', $employee->id)
            ->where('period', $period)
            ->first();

        $this->assertNotNull($summary, 'EMP-TVCT phải có bảng công tháng hiện tại sau seed.');
        $this->assertGreaterThan(0, (float) $summary->probation_work_days);
        $this->assertGreaterThan(0, (float) $summary->official_work_days);
        $this->assertSame('mixed', $summary->attendance_breakdown['meta']['employment_status'] ?? null);

        $this->assertDatabaseHas('employment_contracts', [
            'employee_id' => $employee->id,
            'contract_number' => 'CTR-TVCT-PB',
            'status' => 'expired',
        ]);
        $this->assertDatabaseHas('employment_contracts', [
            'employee_id' => $employee->id,
            'contract_number' => 'CTR-TVCT-CT',
            'status' => 'active',
        ]);

        $allowance = EmployeePayrollAllowance::where('employee_id', $employee->id)
            ->where('period', $period)
            ->first();
        $this->assertNotNull($allowance);
        $this->assertSame(780_000.0, (float) ($allowance->allowances['allowance_meal'] ?? 0));

        $officialContract = EmploymentContract::where('contract_number', 'CTR-TVCT-CT')->first();
        $this->assertSame(16_000_000, (int) $officialContract->salary_base);
        $probationContract = EmploymentContract::where('contract_number', 'CTR-TVCT-PB')->first();
        $this->assertSame(16_000_000, (int) $probationContract->salary_base);
    }

    public function test_regular_sample_employees_have_monthly_attendance_summary(): void
    {
        $this->seed(InitialHrDataSeeder::class);
        $this->seed(HcmPlatformSeeder::class);
        $this->seed(HcmExtendedSeeder::class);
        $this->seed(SampleEmployeesSeeder::class);

        $period = '2026-05';
        $employee = Employee::where('employee_code', 'EMP-102')->first();
        $this->assertNotNull($employee);

        $summary = AttendanceSummary::where('employee_id', $employee->id)
            ->where('period', $period)
            ->first();

        $this->assertNotNull($summary, 'EMP-102 phải có bảng công tháng sau seed.');
        $this->assertGreaterThan(0, (float) $summary->work_days);
        $this->assertGreaterThan(0, (float) $summary->standard_work_days);
    }
}
