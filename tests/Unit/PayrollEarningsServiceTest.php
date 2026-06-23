<?php

namespace Tests\Unit;

use App\Models\AttendanceSummary;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\OvertimeRequest;
use App\Models\Tenant;
use App\Services\Payroll\PayrollEarningsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollEarningsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployeeWithContract(array $empAttrs, array $contractAttrs): array
    {
        $tenant = Tenant::create(['code' => 'T1', 'name' => 'T1']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C1', 'name' => 'C1']);
        $employee = Employee::create(array_merge([
            'company_id' => $company->id,
            'employee_code' => 'E-'.uniqid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'full_name' => 'Test User',
            'email' => uniqid().'@test.local',
            'is_active' => true,
        ], $empAttrs));

        $contract = EmploymentContract::create(array_merge([
            'employee_id' => $employee->id,
            'contract_number' => 'CTR-'.uniqid(),
            'contract_type' => 'fixed_term',
            'status' => 'active',
            'start_date' => '2026-05-01',
        ], $contractAttrs));

        return [$employee, $contract];
    }

    public function test_splits_salary_by_probation_and_official_work_days(): void
    {
        [$employee, $contract] = $this->makeEmployeeWithContract(
            [
                'insurance_salary' => 20_000_000,
                'hire_date' => '2026-05-01',
                'probation_end_date' => '2026-05-15',
            ],
            [
                'salary_base' => 20_000_000,
                'probation_salary' => 17_000_000,
                'probation_months' => 2,
                'insurance_salary' => 20_000_000,
            ],
        );

        $summary = new AttendanceSummary([
            'standard_work_days' => 25,
            'work_days' => 20,
            'probation_work_days' => 10,
            'official_work_days' => 10,
            'leave_days' => 0,
            'absent_days' => 5,
            'ot_hours' => 0,
        ]);

        $service = app(PayrollEarningsService::class);
        $result = $service->calculateGross($employee, $contract, $summary, '2026-05');

        // TV: 17M/25*10 = 6.8M, CT: 20M/25*10 = 8M
        $this->assertEquals(6_800_000, $result['breakdown']['probation_base_pay']);
        $this->assertEquals(8_000_000, $result['breakdown']['official_base_pay']);
        $this->assertEquals(14_800_000, $result['gross_salary']);

        // BHXH prorated by official days only
        $this->assertEquals(8_000_000, $result['insurance_salary_base']);
    }

    public function test_probation_salary_falls_back_to_base_salary_when_not_set(): void
    {
        [$employee, $contract] = $this->makeEmployeeWithContract(
            [
                'insurance_salary' => 20_000_000,
                'hire_date' => '2026-05-01',
                'probation_end_date' => '2026-05-15',
            ],
            [
                'salary_base' => 20_000_000,
                'probation_months' => 2,
            ],
        );

        $summary = new AttendanceSummary([
            'standard_work_days' => 25,
            'work_days' => 20,
            'probation_work_days' => 10,
            'official_work_days' => 10,
            'leave_days' => 0,
            'absent_days' => 5,
            'ot_hours' => 0,
        ]);

        $result = app(PayrollEarningsService::class)->calculateGross($employee, $contract, $summary, '2026-05');

        $this->assertEquals(8_000_000, $result['breakdown']['probation_base_pay']);
        $this->assertEquals(8_000_000, $result['breakdown']['official_base_pay']);
        $this->assertEquals(16_000_000, $result['gross_salary']);
        $this->assertEquals(1.0, $result['breakdown']['probation_salary_rate']);
    }

    public function test_no_bhxh_when_full_month_probation(): void
    {
        [$employee, $contract] = $this->makeEmployeeWithContract(
            [
                'insurance_salary' => 16_000_000,
                'hire_date' => '2026-05-01',
                'probation_end_date' => '2026-06-28',
            ],
            [
                'salary_base' => 16_000_000,
                'probation_salary' => 13_600_000,
                'probation_months' => 2,
            ],
        );

        $summary = new AttendanceSummary([
            'standard_work_days' => 25,
            'work_days' => 24,
            'probation_work_days' => 24,
            'official_work_days' => 0,
            'leave_days' => 0,
            'absent_days' => 1,
        ]);

        $service = app(PayrollEarningsService::class);
        $result = $service->calculateGross($employee, $contract, $summary, '2026-05');

        $this->assertEquals(0, $result['insurance_salary_base']);
        $this->assertGreaterThan(0, $result['breakdown']['probation_base_pay']);
        $this->assertEquals(0, $result['breakdown']['official_base_pay']);
    }

    public function test_paid_leave_adds_to_payable_official_days(): void
    {
        [$employee, $contract] = $this->makeEmployeeWithContract(
            [
                'insurance_salary' => 20_000_000,
                'hire_date' => '2026-05-01',
            ],
            [
                'salary_base' => 20_000_000,
                'probation_months' => 0,
            ],
        );

        $summary = new AttendanceSummary([
            'standard_work_days' => 25,
            'work_days' => 24,
            'probation_work_days' => 0,
            'official_work_days' => 24,
            'leave_days' => 1,
            'paid_leave_days' => 1,
            'unpaid_leave_days' => 0,
            'probation_paid_leave_days' => 0,
            'official_paid_leave_days' => 1,
            'absent_days' => 0,
        ]);

        $service = app(PayrollEarningsService::class);
        $result = $service->calculateGross($employee, $contract, $summary, '2026-05');

        // 25 công tính lương (24 đi làm + 1 phép có lương) → full tháng
        $this->assertEquals(25, $result['breakdown']['payable_official_days']);
        $this->assertEquals(20_000_000, $result['breakdown']['official_base_pay']);
        $this->assertEquals(20_000_000, $result['gross_salary']);
    }

    public function test_unpaid_leave_does_not_add_to_payable_days(): void
    {
        [$employee, $contract] = $this->makeEmployeeWithContract(
            [],
            ['salary_base' => 20_000_000, 'probation_months' => 0],
        );

        $summary = new AttendanceSummary([
            'standard_work_days' => 25,
            'work_days' => 23,
            'probation_work_days' => 0,
            'official_work_days' => 23,
            'leave_days' => 1,
            'paid_leave_days' => 0,
            'unpaid_leave_days' => 1,
            'probation_paid_leave_days' => 0,
            'official_paid_leave_days' => 0,
            'absent_days' => 1,
        ]);

        $service = app(PayrollEarningsService::class);
        $result = $service->calculateGross($employee, $contract, $summary, '2026-05');

        $this->assertEquals(23, $result['breakdown']['payable_official_days']);
        $this->assertEquals(18_400_000, $result['breakdown']['official_base_pay']);
    }

    public function test_ot_pay_uses_probation_and_official_hourly_rates_in_same_month(): void
    {
        [$employee, $contract] = $this->makeEmployeeWithContract(
            [
                'hire_date' => '2026-05-01',
                'probation_end_date' => '2026-05-15',
            ],
            [
                'salary_base' => 20_000_000,
                'probation_salary' => 17_000_000,
                'probation_months' => 2,
            ],
        );

        OvertimeRequest::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'work_date' => '2026-05-10',
            'hours' => 2,
            'night_hours' => 0,
            'ot_type' => 'weekday',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        OvertimeRequest::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'work_date' => '2026-05-20',
            'hours' => 2,
            'night_hours' => 0,
            'ot_type' => 'weekday',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $summary = new AttendanceSummary([
            'standard_work_days' => 25,
            'work_days' => 20,
            'probation_work_days' => 10,
            'official_work_days' => 10,
            'leave_days' => 0,
            'absent_days' => 5,
        ]);

        $result = app(PayrollEarningsService::class)->calculateGross($employee, $contract, $summary, '2026-05');

        $this->assertTrue($result['breakdown']['has_phase_split']);
        $this->assertEquals(2.0, $result['breakdown']['ot_probation_hours']);
        $this->assertEquals(2.0, $result['breakdown']['ot_official_hours']);
        $this->assertGreaterThan(0, $result['breakdown']['ot_probation_pay']);
        $this->assertGreaterThan(0, $result['breakdown']['ot_official_pay']);
        $this->assertGreaterThan(
            $result['breakdown']['ot_probation_pay'],
            $result['breakdown']['ot_official_pay'],
        );
        $this->assertEquals(
            $result['breakdown']['ot_probation_pay'] + $result['breakdown']['ot_official_pay'],
            $result['breakdown']['ot_pay'],
        );
    }

    public function test_zero_work_days_yields_zero_base_pay_and_insurance_base(): void
    {
        [$employee, $contract] = $this->makeEmployeeWithContract(
            ['insurance_salary' => 20_000_000, 'hire_date' => '2026-05-01'],
            ['salary_base' => 20_000_000, 'probation_months' => 0],
        );

        $summary = new AttendanceSummary([
            'standard_work_days' => 25,
            'work_days' => 0,
            'probation_work_days' => 0,
            'official_work_days' => 0,
            'leave_days' => 0,
            'paid_leave_days' => 0,
            'absent_days' => 25,
        ]);

        $result = app(PayrollEarningsService::class)->calculateGross($employee, $contract, $summary, '2026-05');

        $this->assertEquals(0, $result['gross_salary']);
        $this->assertEquals(0, $result['breakdown']['base_pay_total']);
        $this->assertEquals(0, $result['insurance_salary_base']);
    }

    public function test_ot_excess_deduction_splits_by_actual_phase_pay_not_fixed_ratio(): void
    {
        [$employee, $contract] = $this->makeEmployeeWithContract(
            [
                'hire_date' => '2026-05-01',
                'probation_end_date' => '2026-05-15',
            ],
            [
                'salary_base' => 20_000_000,
                'probation_salary' => 17_000_000,
                'probation_months' => 2,
            ],
        );

        $otProbation = OvertimeRequest::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'work_date' => '2026-05-10',
            'hours' => 8,
            'night_hours' => 0,
            'ot_type' => 'weekday',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        OvertimeRequest::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'work_date' => '2026-05-20',
            'hours' => 2,
            'night_hours' => 0,
            'ot_type' => 'weekday',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        \App\Models\OvertimeExcessRecord::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'overtime_request_id' => $otProbation->id,
            'period' => '2026-05',
            'work_date' => '2026-05-10',
            'cap_type' => 'daily',
            'legal_hours' => 4,
            'actual_hours' => 8,
            'excess_hours' => 4,
            'status' => 'pending',
            'exclude_from_payroll' => true,
        ]);

        $summary = new AttendanceSummary([
            'standard_work_days' => 25,
            'work_days' => 20,
            'probation_work_days' => 10,
            'official_work_days' => 10,
            'leave_days' => 0,
            'absent_days' => 5,
        ]);

        $result = app(PayrollEarningsService::class)->calculateGross($employee, $contract, $summary, '2026-05');

        $this->assertEquals(4.0, $result['breakdown']['ot_payroll_excluded_hours']);
        $excludedAmount = (float) $result['breakdown']['ot_payroll_excluded_amount'];
        $this->assertGreaterThan(0, $excludedAmount);

        // 8h TV + 2h CT → phần lớn tiền OT ở TV; trừ vượt mức phải ưu tiên TV, không chia 70/30 CT/TV.
        $this->assertGreaterThan(
            $result['breakdown']['ot_official_pay'],
            $result['breakdown']['ot_probation_pay'],
        );
        $this->assertEquals(
            $result['breakdown']['ot_probation_pay'] + $result['breakdown']['ot_official_pay'],
            $result['breakdown']['ot_pay'],
        );

        $probShare = $result['breakdown']['ot_probation_pay'] / max(1, $result['breakdown']['ot_pay']);
        $this->assertGreaterThan(0.6, $probShare, 'Phần lớn OT còn lại phải thuộc giai đoạn thử việc');
    }
}
