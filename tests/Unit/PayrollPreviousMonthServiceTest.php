<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeReview;
use App\Models\PayrollCycle;
use App\Models\PayrollResult;
use App\Models\PerformanceCycle;
use App\Models\Tenant;
use App\Services\Company\CompanyPolicyResolver;
use App\Services\Payroll\PayrollPreviousMonthService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollPreviousMonthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CompanyContext::set(null);
        CompanyPolicyResolver::flushCache();
        parent::tearDown();
    }

    public function test_performance_bonus_splits_by_previous_month_base_salary_phases(): void
    {
        $tenant = Tenant::create(['code' => 'T1', 'name' => 'T1']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C1', 'name' => 'C1']);

        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'PB001',
            'first_name' => 'KPI',
            'last_name' => 'Split',
            'full_name' => 'KPI Split',
            'email' => 'kpi@test.local',
            'is_active' => true,
        ]);

        $prevCycle = PayrollCycle::create([
            'company_id' => $company->id,
            'period' => '2026-04',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'status' => 'finalized',
        ]);

        PayrollResult::create([
            'payroll_cycle_id' => $prevCycle->id,
            'employee_id' => $employee->id,
            'gross_salary' => 13_200_000,
            'bhxh_employee' => 0,
            'bhxh_employer' => 0,
            'pit_amount' => 0,
            'other_deductions' => 0,
            'net_salary' => 13_200_000,
            'breakdown' => [
                'base_pay_total' => 12_000_000,
                'probation_base_pay' => 4_000_000,
                'official_base_pay' => 8_000_000,
            ],
        ]);

        $performanceCycle = PerformanceCycle::create([
            'tenant_id' => $tenant->id,
            'name' => 'T4/2026',
            'period' => '2026-04',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'status' => 'closed',
        ]);

        EmployeeReview::create([
            'performance_cycle_id' => $performanceCycle->id,
            'employee_id' => $employee->id,
            'self_score' => 80,
            'manager_score' => 80,
            'final_score' => 80,
            'status' => 'completed',
        ]);

        CompanyContext::set($company->id);

        $result = app(PayrollPreviousMonthService::class)->resolveBonus(
            $employee->id,
            $company->id,
            '2026-05',
        );

        $this->assertSame('2026-04', $result['prev_month_period']);
        $this->assertSame(12_000_000.0, $result['prev_month_base_pay']);
        $this->assertSame(4_000_000.0, $result['prev_month_probation_base_pay']);
        $this->assertSame(8_000_000.0, $result['prev_month_official_base_pay']);
        $this->assertSame(80.0, $result['performance_score']);
        $this->assertSame(480_000.0, $result['performance_bonus_probation']);
        $this->assertSame(960_000.0, $result['performance_bonus_official']);
        $this->assertSame(1_440_000.0, $result['performance_bonus']);
        $this->assertTrue($result['performance_bonus_split']);
    }

    public function test_performance_bonus_falls_back_to_work_day_ratio_when_prev_breakdown_lacks_phases(): void
    {
        $tenant = Tenant::create(['code' => 'T2', 'name' => 'T2']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C2', 'name' => 'C2']);

        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'PB002',
            'first_name' => 'Fallback',
            'last_name' => 'Ratio',
            'full_name' => 'Fallback Ratio',
            'email' => 'fallback@test.local',
            'is_active' => true,
        ]);

        $prevCycle = PayrollCycle::create([
            'company_id' => $company->id,
            'period' => '2026-04',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'status' => 'finalized',
        ]);

        PayrollResult::create([
            'payroll_cycle_id' => $prevCycle->id,
            'employee_id' => $employee->id,
            'gross_salary' => 10_000_000,
            'bhxh_employee' => 0,
            'bhxh_employer' => 0,
            'pit_amount' => 0,
            'other_deductions' => 0,
            'net_salary' => 10_000_000,
            'breakdown' => [
                'base_pay_total' => 10_000_000,
            ],
        ]);

        $performanceCycle = PerformanceCycle::create([
            'tenant_id' => $tenant->id,
            'name' => 'T4/2026',
            'period' => '2026-04',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'status' => 'closed',
        ]);

        EmployeeReview::create([
            'performance_cycle_id' => $performanceCycle->id,
            'employee_id' => $employee->id,
            'final_score' => 100,
            'status' => 'completed',
        ]);

        CompanyContext::set($company->id);

        $result = app(PayrollPreviousMonthService::class)->resolveBonus(
            $employee->id,
            $company->id,
            '2026-05',
            [
                'has_phase_split' => true,
                'probation_work_days' => 10,
                'official_work_days' => 12,
            ],
        );

        $this->assertSame(4_545_455.0, $result['prev_month_probation_base_pay']);
        $this->assertSame(5_454_545.0, $result['prev_month_official_base_pay']);
        $this->assertSame(681_818.0, $result['performance_bonus_probation']);
        $this->assertSame(818_182.0, $result['performance_bonus_official']);
        $this->assertSame(1_500_000.0, $result['performance_bonus']);
    }
}
