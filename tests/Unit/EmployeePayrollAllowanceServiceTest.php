<?php

namespace Tests\Unit;

use App\Models\AttendanceSummary;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeePayrollAllowance;
use App\Models\Tenant;
use App\Services\Payroll\EmployeePayrollAllowanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePayrollAllowanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_meal_allowance_per_work_day_uses_attendance_days(): void
    {
        $tenant = Tenant::create(['code' => 'T1', 'name' => 'T1']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C1', 'name' => 'C1']);

        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'MEAL001',
            'first_name' => 'Meal',
            'last_name' => 'Split',
            'full_name' => 'Meal Split',
            'email' => 'meal@test.local',
            'is_active' => true,
        ]);

        EmployeePayrollAllowance::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period' => '2026-04',
            'allowances' => [
                'allowance_meal' => 780_000,
                'allowance_position' => 1_500_000,
            ],
            'travel_support_amount' => 0,
            'travel_eligible' => false,
        ]);

        $summary = AttendanceSummary::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period' => '2026-04',
            'work_days' => 22,
            'probation_work_days' => 10,
            'official_work_days' => 12,
            'standard_work_days' => 22,
            'is_locked' => true,
        ]);

        $merge = app(EmployeePayrollAllowanceService::class)->mergeForPayroll(
            $employee->id,
            $company->id,
            '2026-04',
            $summary,
        );

        $this->assertSame(354_545.0, $merge['fields']['allowance_meal_probation']);
        $this->assertSame(425_455.0, $merge['fields']['allowance_meal_official']);
        $this->assertSame(780_000.0, $merge['fields']['allowance_meal']);
        $this->assertSame(0.0, $merge['fields']['allowance_position_probation']);
        $this->assertSame(818_182.0, $merge['fields']['allowance_position_official']);
        $this->assertSame(1_598_182.0, $merge['taxable_total']);
        $this->assertSame('per_work_day', $merge['phased_allowances']['allowance_meal']['mode']);
    }
}
