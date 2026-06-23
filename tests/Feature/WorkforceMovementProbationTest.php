<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Tenant;
use App\Services\Reports\HrStandardReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkforceMovementProbationTest extends TestCase
{
    use RefreshDatabase;

    public function test_probation_to_official_in_same_period_is_not_counted_as_new_hire(): void
    {
        $tenant = Tenant::create(['code' => 'T1', 'name' => 'T1']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C1', 'name' => 'C1']);

        Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'NV-A',
            'first_name' => 'An',
            'last_name' => 'Nguyen',
            'full_name' => 'Nguyen Van An',
            'email' => 'an@test.local',
            'hire_date' => '2026-04-01',
            'probation_end_date' => '2026-05-05',
            'official_start_date' => '2026-05-06',
            'employment_status' => 'active',
            'is_active' => true,
        ]);

        Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'NV-B',
            'first_name' => 'Binh',
            'last_name' => 'Tran',
            'full_name' => 'Tran Van Binh',
            'email' => 'binh@test.local',
            'hire_date' => '2026-05-10',
            'probation_end_date' => '2026-07-09',
            'official_start_date' => '2026-07-10',
            'employment_status' => 'probation',
            'is_active' => true,
        ]);

        $report = app(HrStandardReportsService::class)->workforceMovement($company->id, null, '2026-05');
        $summary = $report['summary'];

        $this->assertSame(1, $summary['new_hires'], 'Chỉ NV-B (hire tháng 5) là tuyển mới thực sự');
        $this->assertSame(1, $summary['probation_ended_in_period']);
        $this->assertSame(1, $summary['converted_to_official_in_period']);
        $this->assertSame(0, $summary['failed_probation_in_period']);
        $this->assertSame(100.0, $summary['conversion_rate']);
        $this->assertSame(1, $summary['net_headcount_change']);
        $this->assertStringContainsString('chuyển TV→CT không làm tăng tổng headcount', $summary['narrative']);
    }

    public function test_failed_probation_is_counted_separately_from_conversion(): void
    {
        $tenant = Tenant::create(['code' => 'T2', 'name' => 'T2']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C2', 'name' => 'C2']);

        Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'NV-C',
            'first_name' => 'Cuong',
            'last_name' => 'Le',
            'full_name' => 'Le Van Cuong',
            'email' => 'cuong@test.local',
            'hire_date' => '2026-04-01',
            'probation_end_date' => '2026-05-15',
            'official_start_date' => null,
            'termination_date' => '2026-05-16',
            'employment_status' => 'terminated',
            'is_active' => false,
        ]);

        Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'NV-D',
            'first_name' => 'Dung',
            'last_name' => 'Pham',
            'full_name' => 'Pham Van Dung',
            'email' => 'dung@test.local',
            'hire_date' => '2026-04-01',
            'probation_end_date' => '2026-05-20',
            'official_start_date' => '2026-05-21',
            'employment_status' => 'active',
            'is_active' => true,
        ]);

        $report = app(HrStandardReportsService::class)->workforceMovement($company->id, null, '2026-05');
        $summary = $report['summary'];

        $this->assertSame(2, $summary['probation_ended_in_period']);
        $this->assertSame(1, $summary['converted_to_official_in_period']);
        $this->assertSame(1, $summary['failed_probation_in_period']);
        $this->assertSame(50.0, $summary['conversion_rate']);
        $this->assertCount(1, $report['failed_probations']);
        $this->assertCount(1, $report['probation_conversions']);
    }

    public function test_headcount_breakdown_shifts_probation_to_official_without_total_spike(): void
    {
        $tenant = Tenant::create(['code' => 'T3', 'name' => 'T3']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C3', 'name' => 'C3']);

        Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'NV-E',
            'first_name' => 'Em',
            'last_name' => 'Vo',
            'full_name' => 'Vo Thi Em',
            'email' => 'em@test.local',
            'hire_date' => '2026-04-01',
            'probation_end_date' => '2026-05-05',
            'official_start_date' => '2026-05-06',
            'employment_status' => 'active',
            'is_active' => true,
        ]);

        $report = app(HrStandardReportsService::class)->workforceMovement($company->id, null, '2026-05');

        $start = $report['summary']['headcount_start_breakdown'];
        $end = $report['summary']['headcount_end_breakdown'];

        $this->assertSame(1, $start['total']);
        $this->assertSame(1, $start['probation']);
        $this->assertSame(0, $start['official']);

        $this->assertSame(1, $end['total']);
        $this->assertSame(0, $end['probation']);
        $this->assertSame(1, $end['official']);

        $this->assertSame(0, $report['summary']['net_headcount_change']);
    }
}
