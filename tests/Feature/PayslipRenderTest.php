<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\PayrollCycle;
use App\Models\PayrollResult;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payroll\PayslipRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayslipRenderTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithPayroll(): array
    {
        Role::firstOrCreate(['name' => 'admin']);

        $tenant = Tenant::create(['code' => 'T1', 'name' => 'Tenant 1']);
        $company = Company::create([
            'tenant_id' => $tenant->id,
            'code' => 'BPVN',
            'name' => 'CÔNG TY TNHH BESTPACIFIC VIỆT NAM',
        ]);

        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'BP001',
            'first_name' => 'Lan',
            'last_name' => 'Tran',
            'full_name' => 'Tran Thi Lan',
            'email' => 'lan@test.local',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('Admin@123'),
            'default_company_id' => $company->id,
        ]);
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'admin-tok-'.uniqid()])->save();

        CompanySetting::create([
            'company_id' => $company->id,
            'key' => 'payslip_template_code',
            'value' => 'bpvn-ac-pr-006',
        ]);

        $cycle = PayrollCycle::create([
            'company_id' => $company->id,
            'period' => '2026-05',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'status' => 'calculated',
        ]);

        $result = PayrollResult::create([
            'payroll_cycle_id' => $cycle->id,
            'employee_id' => $employee->id,
            'gross_salary' => 18000000,
            'bhxh_employee' => 945000,
            'bhxh_employer' => 0,
            'pit_amount' => 350000,
            'other_deductions' => 0,
            'net_salary' => 16705000,
            'breakdown' => [
                'base_salary_monthly' => 15000000,
                'standard_work_days' => 25,
                'work_days' => 22,
                'official_work_days' => 22,
                'payable_probation_days' => 0,
                'payable_official_days' => 23,
                'paid_leave_days' => 1,
                'unpaid_leave_days' => 0,
                'base_pay_total' => 14500000,
                'ot_hours' => 8,
                'ot_pay' => 1200000,
                'diligence_bonus_pay' => 500000,
                'performance_bonus' => 1800000,
                'payslip_attendance' => [
                    'standard_work_days' => 25,
                    'work_days' => 22,
                    'payable_official_days' => 23,
                    'payable_total_days' => 23,
                    'paid_leave_days' => 1,
                    'has_phase_split' => false,
                ],
            ],
        ]);

        return [$user, $company, $result];
    }

    public function test_bpvn_payslip_renders_vietnamese_template(): void
    {
        [$user, $company, $result] = $this->adminWithPayroll();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->get('/api/v1/payroll-results/'.$result->id.'/payslip');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $html = $response->getContent();
        $this->assertStringContainsString('BPVN-AC-PR-006 A/1', $html);
        $this->assertStringContainsString('Phiếu lương tháng 05 năm 2026', $html);
        $this->assertStringNotContainsString('项目', $html);
        $this->assertStringContainsString('Lương thực lĩnh', $html);
        $this->assertStringContainsString('16.705.000', $html);
        $this->assertStringContainsString('BP001', $html);
        $this->assertStringContainsString('Tran Thi Lan', $html);
        $this->assertStringContainsString('22,0 / 25 / 23,0', $html);
    }

    public function test_payslip_render_service_falls_back_to_simple_template(): void
    {
        [$user, $company, $result] = $this->adminWithPayroll();

        CompanySetting::where('company_id', $company->id)
            ->where('key', 'payslip_template_code')
            ->update(['value' => 'simple']);

        $html = app(PayslipRenderService::class)->render($result->fresh(['employee', 'cycle', 'payslip']));

        $this->assertStringContainsString('PHIẾU LƯƠNG', $html);
        $this->assertStringContainsString('Đi làm', $html);
        $this->assertStringNotContainsString('BPVN-AC-PR-006 A/1', $html);
    }
}
