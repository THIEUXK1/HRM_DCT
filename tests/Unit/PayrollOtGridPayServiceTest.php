<?php

namespace Tests\Unit;

use App\Models\AttendanceSummary;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\OvertimeRequest;
use App\Models\Tenant;
use App\Services\Payroll\PayrollOtGridPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollOtGridPayServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_day_and_night_ot_with_bpvn_multipliers(): void
    {
        $tenant = Tenant::create(['code' => 'T1', 'name' => 'T1']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'BP', 'name' => 'BP']);

        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'V260865',
            'first_name' => 'Minh',
            'last_name' => 'Chang',
            'full_name' => 'Chang A Minh',
            'email' => 'minh@test.local',
            'is_active' => true,
        ]);

        $contract = EmploymentContract::create([
            'employee_id' => $employee->id,
            'contract_number' => 'CTR-1',
            'contract_type' => 'fixed_term',
            'status' => 'active',
            'start_date' => '2026-05-01',
            'salary_base' => 5_500_000,
            'probation_salary' => 5_500_000,
        ]);

        OvertimeRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-05-20',
            'hours' => 32,
            'night_hours' => 0,
            'ot_type' => 'weekday',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        OvertimeRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-05-24',
            'hours' => 24,
            'night_hours' => 4,
            'ot_type' => 'weekend',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        OvertimeRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-05-25',
            'hours' => 24,
            'night_hours' => 0,
            'ot_type' => 'holiday',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $summary = new AttendanceSummary([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period' => '2026-05',
            'standard_work_days' => 26,
            'work_days' => 8,
        ]);

        $result = app(PayrollOtGridPayService::class)->calculate($employee, $contract, $summary, '2026-05');

        $this->assertEquals('bpvn_ot_grid', $result['calculation_method']);
        $this->assertEquals(32.0, $result['hour_grid']['day_weekday']);
        $this->assertEquals(20.0, $result['hour_grid']['day_weekend']);
        $this->assertEquals(4.0, $result['hour_grid']['night_weekend']);
        $this->assertEquals(24.0, $result['hour_grid']['day_holiday']);

        $hourly = 5_500_000 / 26 / 8;
        $expectedDayWeekday = round($hourly * 32 * 1.5, 0);
        $expectedDayWeekend = round($hourly * 20 * 2.0, 0);
        $expectedNightWeekend = round($hourly * 4 * 2.7, 0);
        $expectedDayHoliday = round($hourly * 24 * 3.0, 0);

        $this->assertEquals($expectedDayWeekday, $result['pay_grid']['day_weekday']);
        $this->assertEquals($expectedDayWeekend, $result['pay_grid']['day_weekend']);
        $this->assertEquals($expectedNightWeekend, $result['pay_grid']['night_weekend']);
        $this->assertEquals($expectedDayHoliday, $result['pay_grid']['day_holiday']);

        $this->assertEquals(
            $expectedDayWeekday + $expectedDayWeekend + $expectedDayHoliday,
            $result['day_pay'],
        );
        $this->assertEquals($expectedNightWeekend, $result['night_pay']);
        $this->assertEquals($result['day_pay'] + $result['night_pay'], $result['total']);
    }
}
